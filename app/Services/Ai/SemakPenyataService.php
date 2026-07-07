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

        // Kunci per-masjid: semakan kuota + cipta batch mesti atomik — halang
        // dua muat naik selari memintas had (TOCTOU). Degrade: gagal mesra.
        $lock = \Illuminate\Support\Facades\Cache::lock('semak-penyata:'.$masjidId, 15);
        if (!$lock->get()) {
            throw new InvalidArgumentException('Muat naik lain sedang diproses — sila cuba sebentar lagi.');
        }

        try {
            return $this->muatNaikDalamKunci($bankAccountId, $fail, $masjidId);
        } finally {
            $lock->release();
        }
    }

    private function muatNaikDalamKunci(int $bankAccountId, UploadedFile $fail, int $masjidId): PenyataSemakan
    {
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

        // Batch GAGAL tidak mengunci fail — guna semula baris sama (patuh
        // UNIQUE uq_ps_hash): reset ke UPLOADED, buang baris/fail lama, dispatch semula.
        if ($wujud && $wujud->status === 'GAGAL') {
            return $this->cubaSemula($wujud, $fail, $masjidId);
        }
        if ($wujud) {
            throw new InvalidArgumentException('Penyata ini telah dimuat naik sebelum ini (batch #'.$wujud->id.').');
        }

        $ext = $this->extDariMime($fail);

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

    /** Muat naik semula fail yang batch-nya GAGAL — guna semula rekod (kuota dikira semula). */
    private function cubaSemula(PenyataSemakan $batch, UploadedFile $fail, int $masjidId): PenyataSemakan
    {
        DB::transaction(function () use ($batch, $fail, $masjidId) {
            // Buang baris separa (jika ada) + fail lama supaya proses bermula bersih.
            $batch->baris()->delete();
            if ($batch->file_path && Storage::disk('local')->exists($batch->file_path)) {
                Storage::disk('local')->delete($batch->file_path);
            }

            $path = 'penyata-ai/'.$masjidId.'/'.$batch->id.'.'.$this->extDariMime($fail);
            Storage::disk('local')->put($path, file_get_contents($fail->getRealPath()));

            $batch->update([
                'status' => 'UPLOADED',
                'file_path' => $path,
                'original_name' => mb_substr($fail->getClientOriginalName(), 0, 200),
                'mime' => $fail->getMimeType() ?: 'application/octet-stream',
                'error_text' => null,
                'bil_baris' => 0,
                'bil_auto_padan' => 0,
                'tokens_used' => null,
                'cost_usd' => null,
                'uploaded_by' => app()->bound('current.user_id') ? app('current.user_id') : null,
            ]);
        });

        $this->audit->log('UPDATE', 'penyata_semakan', ['status' => 'GAGAL'],
            ['status' => 'UPLOADED', 'nota' => 'cuba semula selepas gagal'], $batch->id);

        ProsesPenyataAi::dispatch($batch->id);

        return $batch->fresh();
    }

    /** Sambungan fail daripada mime yang DISAHKAN kandungan (bukan nama klien). */
    private function extDariMime(UploadedFile $fail): string
    {
        return match ($fail->getMimeType()) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            default => 'jpg',
        };
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

        // Kuatkuasa keluarga COA di PELAYAN (UI hanya panduan): wang masuk =
        // hasil 400/450 atau tabung liabiliti 300-04; keluar = belanja 600/650.
        // Tanpa ini, jurnal tetap seimbang tetapi Untung Rugi jadi salah kelas.
        $kod = (string) \App\Models\Coa::whereKey($coaId)->value('kod');
        $sah = $masuk
            ? (str_starts_with($kod, '400-') || str_starts_with($kod, '450-') || str_starts_with($kod, '300-04'))
            : (str_starts_with($kod, '600-') || str_starts_with($kod, '650-'));
        if (!$sah) {
            throw new InvalidArgumentException($masuk
                ? 'Wang masuk mesti direkod ke kod hasil (400/450) atau tabung (300-04xxx).'
                : 'Wang keluar mesti direkod ke kod belanja (600/650).');
        }

        return DB::transaction(function () use ($line, $data, $masuk, $jenis, $jumlah, $tarikh, $coaId) {
            // Kunci baris & semak semula status DALAM transaksi — halang klik
            // berganda / dua tab merekod baris sama dua kali (jurnal berganda).
            $terkini = BankStatementLine::whereKey($line->id)->lockForUpdate()->first();
            if (!$terkini || $terkini->status !== 'UNMATCHED') {
                throw new InvalidArgumentException('Baris ini telah pun direkod atau diabaikan.');
            }

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
