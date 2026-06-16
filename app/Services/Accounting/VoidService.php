<?php

namespace App\Services\Accounting;

use App\Enums\SourceType;
use App\Exceptions\AlreadyVoidedException;
use App\Models\JournalEntry;
use App\Models\JournalVoucher;
use App\Services\Security\AuditTrailService;
use Illuminate\Support\Facades\DB;

/**
 * "Padam" dalam sistem ini = VOID + voucher pembalik (BUKAN hard-delete
 * seperti SPPKMS asal — keputusan reka bentuk yang disahkan: jejak audit kekal).
 *
 * KONVENSYEN LAPORAN: semua laporan menapis status='POSTED'. Voucher asal
 * DAN voucher pembalik kedua-duanya bertaraf VOID — kedua-duanya dikecualikan
 * daripada laporan, jadi setiap laporan berkelakuan TEPAT seperti "padam"
 * SPPKMS asal (residual KOSONG, disahkan empirik), sementara baris kekal
 * dalam DB sebagai jejak audit. Pembalik mendokumenkan pembatalan secara
 * eksplisit untuk juruaudit (Dr/Cr diterbalikkan, tarikh & period asal).
 */
class VoidService
{
    public function __construct(
        private PeriodService $period,
        private AuditTrailService $audit,
    ) {
    }

    public function voidVoucher(JournalVoucher $voucher, string $sebab = '', ?int $userId = null): JournalVoucher
    {
        $userId ??= app()->bound('current.user_id') ? app('current.user_id') : null;

        return DB::transaction(function () use ($voucher, $sebab, $userId) {
            $v = JournalVoucher::withoutMasjidScope()->whereKey($voucher->id)->lockForUpdate()->first();

            if ($v->status === 'VOID') {
                throw new AlreadyVoidedException($v->voucher_ref);
            }

            // Tempoh terkunci = tidak boleh batal (laporan tahun lepas muktamad)
            $this->period->assertOpen($v->period_ym ?? substr($v->tarikh->format('Y-m-d'), 0, 7), $v->masjid_id);

            $v->update([
                'status'    => 'VOID',
                'voided_by' => $userId,
                'voided_at' => now(),
            ]);

            $entries = JournalEntry::where('voucher_id', $v->id)->get();

            $pembalik = JournalVoucher::withoutMasjidScope()->create([
                'masjid_id'   => $v->masjid_id,
                'voucher_ref' => mb_substr('RV-'.$v->voucher_ref, 0, 40),
                'tarikh'      => $v->tarikh,
                'period_ym'   => $v->period_ym,
                'deskripsi'   => mb_substr("PEMBALIK {$v->voucher_ref}".($sebab ? " — {$sebab}" : ''), 0, 500),
                'source_type' => $v->source_type instanceof SourceType ? $v->source_type->value : $v->source_type,
                'source_id'   => $v->source_id,
                'status'      => 'VOID', // dokumentasi audit — dikecualikan dari laporan (tapis POSTED)
                'created_by'  => $userId,
                'voided_by'   => $userId,
                'voided_at'   => now(),
            ]);

            JournalEntry::insert($entries->map(fn ($e) => [
                'voucher_id' => $pembalik->id,
                'coa_id'     => $e->coa_id,
                'memo'       => mb_substr('Pembalik: '.$e->memo, 0, 300),
                'debit'      => $e->kredit,
                'kredit'     => $e->debit,
            ])->all());

            $this->audit->log('VOID', 'journal_voucher', null, [
                'voucher_ref' => $v->voucher_ref,
                'pembalik'    => $pembalik->voucher_ref,
                'sebab'       => $sebab,
            ], $v->id, $userId, $v->masjid_id);

            return $pembalik;
        });
    }
}
