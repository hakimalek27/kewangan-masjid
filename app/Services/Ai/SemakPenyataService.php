<?php

namespace App\Services\Ai;

use App\Jobs\ProsesPenyataAi;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\PenyataSemakan;
use App\Services\Security\AuditTrailService;
use App\Services\Transaksi\KutipanService;
use App\Services\Transaksi\PembayaranService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Ciri "Semak Penyata (AI)" — muat naik penyata, proses AI, dan rekod baris.
 * "Rekod" satu baris = catat kutipan/belanja SEBENAR (laluan tulis tunggal
 * KutipanService/PembayaranService → JournalService::post). AI tidak pernah pos.
 */
class SemakPenyataService
{
    public function __construct(
        private KutipanService $kutipan,
        private PembayaranService $pembayaran,
        private KuotaPenyataService $kuota,
        private AuditTrailService $audit,
    ) {
    }

    /** Muat naik penyata → cipta batch UPLOADED + dispatch job AI. */
    public function muatNaik(int $bankAccountId, UploadedFile $fail): PenyataSemakan
    {
        $masjidId = (int) app('current.masjid_id');

        if (!$this->kuota->boleh($masjidId)) {
            $baki = $this->kuota->remaining($masjidId);
            $had = $this->kuota->effectiveLimit($masjidId);
            throw new InvalidArgumentException(
                $this->kuota->globallyEnabled()
                    ? "Kuota bulanan Semak Penyata telah habis (baki {$baki}/{$had}). Sila hubungi pentadbir sistem untuk top-up."
                    : 'Ciri Semak Penyata (AI) tidak aktif buat masa ini.'
            );
        }

        // Sahkan bank milik masjid (scope global) — anti IDOR.
        $bank = BankAccount::findOrFail($bankAccountId);

        $hash = hash_file('sha256', $fail->getRealPath());
        $wujud = PenyataSemakan::where('file_hash', $hash)->first();
        if ($wujud) {
            throw new InvalidArgumentException('Penyata ini telah dimuat naik sebelum ini (batch #'.$wujud->id.').');
        }

        $ext = strtolower($fail->getClientOriginalExtension() ?: 'bin');

        $batch = DB::transaction(function () use ($bank, $fail, $hash, $ext, $masjidId) {
            $batch = PenyataSemakan::create([
                'bank_account_id' => $bank->id,
                'file_path' => '', // diisi selepas ada id
                'original_name' => mb_substr($fail->getClientOriginalName(), 0, 200),
                'mime' => $fail->getMimeType() ?: 'application/octet-stream',
                'file_hash' => $hash,
                'status' => 'UPLOADED',
                'uploaded_by' => app()->bound('current.user_id') ? app('current.user_id') : null,
            ]);

            $path = 'penyata-ai/'.$masjidId.'/'.$batch->id.'.'.$ext;
            Storage::disk('local')->put($path, file_get_contents($fail->getRealPath()));
            $batch->update(['file_path' => $path]);

            return $batch;
        });

        $this->audit->log('CREATE', 'penyata_semakan', null, [
            'bank' => $bank->nama_bank, 'batch' => $batch->id, 'fail' => $batch->original_name,
        ], $batch->id);

        ProsesPenyataAi::dispatch($batch->id);

        return $batch;
    }

    /**
     * Rekod satu baris penyata → catat kutipan (masuk) / belanja (keluar).
     * Pulangkan ['jenis','recno','voucher_id'].
     */
    public function rekodBaris(BankStatementLine $line, array $data): array
    {
        $this->pastikanBolehRekod($line);

        $masuk = (float) $line->kredit > 0;
        $jenis = $masuk ? 'KUTIPAN' : 'BAYARAN';
        $jumlah = $masuk ? (string) $line->kredit : (string) $line->debit;
        $tarikh = $line->tarikh instanceof \DateTimeInterface
            ? $line->tarikh->format('Y-m-d') : (string) $line->tarikh;

        $coaId = (int) ($data['coa_id'] ?? 0);
        if ($coaId <= 0) {
            throw new InvalidArgumentException('Sila pilih kod akaun (COA).');
        }

        return DB::transaction(function () use ($line, $data, $masuk, $jenis, $jumlah, $tarikh, $coaId) {
            if ($masuk) {
                $rekod = $this->kutipan->create([
                    'jenis' => 'BIASA',
                    'tarikh' => $tarikh,
                    'coa_id' => $coaId,
                    'kaedah' => 'BANK_TRANSFER_QR',
                    'jumlah' => $jumlah,
                    'auto_resit' => true,
                    'nama_pemberi' => $data['penerima'] ?? null,
                    'bank_account_id' => (int) $line->bank_account_id,
                    'deskripsi' => $data['deskripsi'] ?? $line->deskripsi,
                ]);
            } else {
                $rekod = $this->pembayaran->createBayaran([
                    'tar_lulus' => $tarikh,
                    'coa_id' => $coaId,
                    'jumlah' => $jumlah,
                    'cara_bayar' => 'EFT',
                    'auto_baucer' => true,
                    'pemohon' => $data['penerima'] ?? null,
                    'deskripsi' => $data['deskripsi'] ?? $line->deskripsi,
                    'bank_account_id' => (int) $line->bank_account_id,
                ]);
            }

            // Voucher SEGAR → invarian padanAuto (voucher dipadan ≤1 baris) kekal.
            $line->update(['status' => 'MATCHED', 'matched_voucher_id' => $rekod->voucher_id]);

            $this->audit->log('UPDATE', 'bank_statement_line',
                ['status' => 'UNMATCHED'],
                ['status' => 'MATCHED', 'jenis' => $jenis, 'recno' => $rekod->id, 'voucher' => $rekod->voucher_id],
                $line->id);

            return ['jenis' => $jenis, 'recno' => $rekod->id, 'voucher_id' => $rekod->voucher_id];
        });
    }

    /** Abai satu baris (tidak akan direkod). */
    public function abaiBaris(BankStatementLine $line): void
    {
        $this->pastikanBolehRekod($line);
        $line->update(['status' => 'IGNORED', 'matched_voucher_id' => null]);

        $this->audit->log('UPDATE', 'bank_statement_line',
            ['status' => 'UNMATCHED'], ['status' => 'IGNORED'], $line->id);
    }

    /** Baris mesti milik batch SEDIA masjid semasa + belum dipadan. */
    private function pastikanBolehRekod(BankStatementLine $line): void
    {
        if (!$line->batch_id) {
            throw new InvalidArgumentException('Baris ini bukan daripada batch Semak Penyata.');
        }
        // PenyataSemakan berskop masjid → silang-tenant = tidak ditemui.
        $batch = PenyataSemakan::whereKey($line->batch_id)->first();
        if (!$batch || $batch->status !== 'SEDIA') {
            throw new InvalidArgumentException('Batch penyata belum sedia atau tidak sah.');
        }
        if ($line->status !== 'UNMATCHED') {
            throw new InvalidArgumentException('Baris ini telah pun direkod atau diabaikan.');
        }
    }
}
