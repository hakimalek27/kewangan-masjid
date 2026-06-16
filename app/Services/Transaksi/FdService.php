<?php

namespace App\Services\Transaksi;

use App\Enums\SourceType;
use App\Models\FdInvestment;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\VoidService;
use App\Services\Security\AuditTrailService;
use Illuminate\Support\Facades\DB;

/**
 * Pelaburan / Simpanan Tetap (FD) — matriks Dr/Cr:
 *   FD Baru : Dr 250-040x0 FD / Cr Bank  → pemindahan aset (BUKAN P&L)
 *   Matang  : Dr Bank / Cr FD            → pemindahan balik
 *   Dividen : melalui KutipanService::createDividen (Dr Bank / Cr 450-xxx)
 * Padam FD TIDAK menyentuh dividennya (tingkah laku SPPKMS dikekalkan —
 * dividen dipadam berasingan melalui kutipan).
 */
class FdService
{
    public function __construct(
        private JournalService $journal,
        private VoidService $void,
        private AuditTrailService $audit,
    ) {
    }

    public function create(array $data): FdInvestment
    {
        return DB::transaction(function () use ($data) {
            $fd = FdInvestment::create([
                'tarikh'        => $data['tarikh'],
                'institusi'     => $data['institusi'],
                'coa_fd_id'     => $data['coa_fd_id'],
                'coa_bank_id'   => $data['coa_bank_id'],
                'jumlah'        => $data['jumlah'],
                'kadar_pct'     => $data['kadar_pct'] ?? null,
                'tempoh_bulan'  => $data['tempoh_bulan'] ?? null,
                'maturity_date' => $data['maturity_date'] ?? null,
                'no_sijil'      => $data['no_sijil'] ?? null,
                'keterangan'    => $data['keterangan'] ?? null,
                'is_opening'    => 0,
                'status'        => 'AKTIF',
            ]);

            $voucher = $this->journal->post(
                tarikh: $data['tarikh'],
                sourceType: SourceType::FD,
                sourceId: $fd->id,
                deskripsi: 'FD BARU: '.$data['institusi'].' '.($data['no_sijil'] ?? ''),
                lines: [
                    ['coa_id' => $data['coa_fd_id'], 'debit' => $data['jumlah'], 'kredit' => 0, 'memo' => $data['no_sijil'] ?? ''],
                    ['coa_id' => $data['coa_bank_id'], 'debit' => 0, 'kredit' => $data['jumlah'], 'memo' => $data['no_sijil'] ?? ''],
                ],
                voucherRef: 'FD'.str_pad((string) $fd->id, 6, '0', STR_PAD_LEFT),
            );

            $fd->update(['voucher_id' => $voucher->id]);
            $this->audit->log('CREATE', 'fd_investment', null, ['institusi' => $data['institusi'], 'jumlah' => (string) $data['jumlah']], $fd->id);

            return $fd->fresh();
        });
    }

    /** Daftar Pelaburan Lama (opening) — daftar sahaja, TIADA jurnal (nilai melalui Baki Awal). */
    public function createOpening(array $data): FdInvestment
    {
        $fd = FdInvestment::create([...$data, 'is_opening' => 1, 'status' => 'AKTIF']);
        $this->audit->log('CREATE', 'fd_investment', null, ['opening' => true, 'institusi' => $data['institusi']], $fd->id);

        return $fd;
    }

    /** FD matang — wang kembali ke bank: Dr Bank / Cr FD. */
    public function mature(FdInvestment $fd, string $tarikh): FdInvestment
    {
        return DB::transaction(function () use ($fd, $tarikh) {
            $this->journal->post(
                tarikh: $tarikh,
                sourceType: SourceType::FD,
                sourceId: $fd->id,
                deskripsi: 'FD MATANG: '.$fd->institusi.' '.($fd->no_sijil ?? ''),
                lines: [
                    ['coa_id' => $fd->coa_bank_id, 'debit' => (string) $fd->jumlah, 'kredit' => 0],
                    ['coa_id' => $fd->coa_fd_id, 'debit' => 0, 'kredit' => (string) $fd->jumlah],
                ],
                voucherRef: 'FDM'.str_pad((string) $fd->id, 6, '0', STR_PAD_LEFT),
            );

            $fd->update(['status' => 'MATANG']);
            $this->audit->log('UPDATE', 'fd_investment', ['status' => 'AKTIF'], ['status' => 'MATANG'], $fd->id);

            return $fd->fresh();
        });
    }

    public function renew(FdInvestment $fd): FdInvestment
    {
        $fd->update(['status' => 'DIPERBAHARUI']);
        $this->audit->log('UPDATE', 'fd_investment', null, ['status' => 'DIPERBAHARUI'], $fd->id);

        return $fd->fresh();
    }

    /**
     * Kemaskini HANYA medan bukan-kewangan FD. Jumlah, COA FD & COA bank
     * TIDAK boleh diubah kerana ia menjejaskan jurnal yang sudah POSTED —
     * sebarang medan kewangan dalam $data diabaikan secara senyap.
     */
    public function kemaskini(FdInvestment $fd, array $data): FdInvestment
    {
        $dibenarkan = ['institusi', 'no_sijil', 'kadar_pct', 'tempoh_bulan', 'maturity_date', 'keterangan'];
        $perubahan = array_intersect_key($data, array_flip($dibenarkan));

        $sebelum = $fd->only($dibenarkan);
        $fd->update($perubahan);
        $this->audit->log('UPDATE', 'fd_investment', $sebelum, $fd->only($dibenarkan), $fd->id);

        return $fd->fresh();
    }

    /** Padam FD = VOID voucher + status DIPADAM. Dividen TIDAK disentuh. */
    public function void(FdInvestment $fd, string $sebab = ''): void
    {
        DB::transaction(function () use ($fd, $sebab) {
            if ($fd->voucher_id) {
                $this->void->voidVoucher(\App\Models\JournalVoucher::withoutMasjidScope()->findOrFail($fd->voucher_id), $sebab);
            }
            $fd->update(['status' => 'DIPADAM']);
            $this->audit->log('DELETE', 'fd_investment', ['institusi' => $fd->institusi], null, $fd->id);
        });
    }
}
