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

    /**
     * Muat naik penyata → cipta batch UPLOADED + dispatch job AI.
     * $spProviderId = profil provider pilihan (null → guna default aktif) supaya
     * penyata sama boleh discan oleh provider berbeza untuk BANDING kualiti OCR.
     */
    public function muatNaik(int $bankAccountId, UploadedFile $fail, ?int $spProviderId = null): PenyataSemakan
    {
        $masjidId = (int) app('current.masjid_id');

        // Selesaikan provider pilihan → default aktif jika tiada. 0 = legasi (tiada profil).
        if (!$spProviderId) {
            $spProviderId = \App\Models\SpProvider::aktif()->value('id');
        }
        $provider = $spProviderId ? \App\Models\SpProvider::find($spProviderId) : null;
        $providerId = (int) ($provider?->id ?? 0);
        $providerLabel = $provider?->label();

        // Kunci per-masjid: semakan kuota + cipta batch mesti atomik — halang
        // dua muat naik selari memintas had (TOCTOU). Degrade: gagal mesra.
        $lock = \Illuminate\Support\Facades\Cache::lock('semak-penyata:'.$masjidId, 15);
        if (!$lock->get()) {
            throw new InvalidArgumentException('Muat naik lain sedang diproses — sila cuba sebentar lagi.');
        }

        try {
            return $this->muatNaikDalamKunci($bankAccountId, $fail, $masjidId, $providerId, $providerLabel);
        } finally {
            $lock->release();
        }
    }

    private function muatNaikDalamKunci(int $bankAccountId, UploadedFile $fail, int $masjidId, int $providerId, ?string $providerLabel): PenyataSemakan
    {
        // Sahkan bank milik masjid (scope global) — anti IDOR.
        $bank = BankAccount::findOrFail($bankAccountId);

        $hash = hash_file('sha256', $fail->getRealPath());
        // Dedup PER-provider: penyata sama boleh discan sekali setiap provider (banding).
        $wujud = PenyataSemakan::where('file_hash', $hash)->where('sp_provider_id', $providerId)->first();

        // Batch tidak-aktif (GAGAL/DIBATAL/DIPADAM) TIDAK mengunci fail — GUNA SEMULA
        // slot sedia ada (re-scan fail SAMA yang tenant sudah muat naik) tanpa memerlukan
        // kuota BAHARU: reset ke UPLOADED, buang baris/fail lama, dispatch semula.
        if ($wujud && in_array($wujud->status, ['GAGAL', 'DIBATAL', 'DIPADAM'], true)) {
            return $this->cubaSemula($wujud, $fail, $masjidId);
        }
        if ($wujud) {
            throw new InvalidArgumentException('Penyata ini telah dimuat naik dengan provider yang sama sebelum ini (batch #'.$wujud->id.'). Padam batch itu dahulu untuk scan semula, atau pilih provider lain untuk banding.');
        }

        // Muat naik BAHARU (fail belum pernah discan provider ini) → semak kuota.
        if (!$this->kuota->boleh($masjidId)) {
            $baki = $this->kuota->remaining($masjidId);
            $had = $this->kuota->effectiveLimit($masjidId);
            throw new InvalidArgumentException(
                $this->kuota->globallyEnabled()
                    ? "Kuota bulanan Semak Penyata telah habis (baki {$baki}/{$had}). Sila hubungi pentadbir sistem untuk top-up."
                    : 'Ciri Semak Penyata (AI) tidak aktif buat masa ini.'
            );
        }

        $ext = $this->extDariMime($fail);

        $batch = DB::transaction(function () use ($bank, $fail, $hash, $ext, $masjidId, $providerId, $providerLabel) {
            $batch = PenyataSemakan::create([
                'bank_account_id' => $bank->id,
                'sp_provider_id' => $providerId,
                'provider_label' => $providerLabel,
                'file_path' => '', // diisi selepas ada id
                'original_name' => mb_substr($fail->getClientOriginalName(), 0, 200),
                'mime' => $fail->getMimeType() ?: 'application/octet-stream',
                'file_hash' => $hash,
                'status' => 'UPLOADED',
                'uploaded_by' => app()->bound('current.user_id') ? app('current.user_id') : null,
                // Bukti persetujuan PDPA — tenant SETUJU kongsi penyata bank (data peribadi).
                'pdpa_setuju_oleh' => app()->bound('current.user_id') ? app('current.user_id') : null,
                'pdpa_setuju_pada' => now(),
            ]);

            // Stream fail ke disk (putFileAs) — elak muat keseluruhan (≤100MB) ke memori.
            $path = Storage::disk('local')->putFileAs('penyata-ai/'.$masjidId, $fail, $batch->id.'.'.$ext);
            $batch->update(['file_path' => $path]);

            return $batch;
        });

        $this->audit->log('CREATE', 'penyata_semakan', null, [
            'bank' => $bank->nama_bank, 'batch' => $batch->id, 'fail' => $batch->original_name,
            'pdpa_setuju' => true, // tenant bersetuju kongsi penyata bank (bukti PDPA)
        ], $batch->id);

        ProsesPenyataAi::dispatch($batch->id);

        return $batch;
    }

    /**
     * Padam batch penyata + fail asal secara KEKAL (tenant mesti scan semula untuk
     * dapat balik). Baris yang SUDAH direkod ke lejar (MATCHED) DIKEKALKAN — hanya
     * dilepaskan kaitan batch supaya rekod kewangan/audit tidak terjejas; baris
     * belum direkod (UNMATCHED/IGNORED) dibuang. Tidak boleh padam semasa proses.
     */
    public function padamBatch(PenyataSemakan $batch): void
    {
        if (in_array($batch->status, ['UPLOADED', 'AI_PROCESSING'], true)) {
            throw new InvalidArgumentException('Penyata sedang diproses — batalkan pemprosesan dahulu sebelum padam.');
        }

        $id = $batch->id;
        $nama = $batch->original_name;
        // Scan SIAP (SEDIA) sudah GUNA kuota → simpan rekod tombstone (DIPADAM)
        // supaya memadam fail TIDAK memulihkan kuota (elak pintas had). Batch
        // GAGAL/DIBATAL tidak mengira kuota → boleh dibuang terus.
        $kekalUntukKuota = $batch->status === 'SEDIA';

        DB::transaction(function () use ($batch, $kekalUntukKuota) {
            // Rekod sebenar (MATCHED) dilindungi: lepas kaitan batch, JANGAN padam.
            $batch->baris()->where('status', 'MATCHED')->update(['batch_id' => null]);
            $batch->baris()->whereIn('status', ['UNMATCHED', 'IGNORED'])->delete();

            if ($batch->file_path && Storage::disk('local')->exists($batch->file_path)) {
                Storage::disk('local')->delete($batch->file_path);
            }

            if ($kekalUntukKuota) {
                $batch->update([
                    'status' => 'DIPADAM',
                    'file_path' => '',
                    'error_text' => 'Fail & data penyata telah dipadam oleh pengguna. Kuota kekal digunakan.',
                ]);
            } else {
                $batch->delete();
            }
        });

        $this->audit->log('DELETE', 'penyata_semakan',
            ['batch' => $id, 'fail' => $nama],
            ['nota' => $kekalUntukKuota
                ? 'padam fail+data (rekod DIPADAM kekal utk kuota); baris MATCHED dikekalkan'
                : 'padam kekal batch GAGAL/DIBATAL'], $id);
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

            $path = Storage::disk('local')->putFileAs('penyata-ai/'.$masjidId, $fail, $batch->id.'.'.$this->extDariMime($fail));

            $batch->update([
                'status' => 'UPLOADED',
                'file_path' => $path,
                'original_name' => mb_substr($fail->getClientOriginalName(), 0, 200),
                'mime' => $fail->getMimeType() ?: 'application/octet-stream',
                'error_text' => null,
                'bil_baris' => 0,
                'bil_auto_padan' => 0,
                'tokens_used' => null,
                'prompt_tokens' => null,
                'completion_tokens' => null,
                'cost_usd' => null,
                'kaedah' => null,
                'muka_jumlah' => null,
                'muka_siap' => null,
                'penyata_jum_debit' => null,
                'penyata_jum_kredit' => null,
                'batal_diminta' => false,
                'pdpa_setuju_oleh' => app()->bound('current.user_id') ? app('current.user_id') : null,
                'pdpa_setuju_pada' => now(),
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

    /**
     * Rekod BEBERAPA baris sebagai SATU rekod lump-sum (cth longgok infaq QR kecil
     * hari sama jadi satu kutipan). Semua baris mesti SATU sisi (semua masuk ATAU
     * semua keluar) & akaun bank sama. Pulangkan ['jenis','recno','voucher_id','bil','jumlah'].
     */
    public function rekodLumpSum(array $lineIds, array $data): array
    {
        $ids = array_values(array_unique(array_map('intval', $lineIds)));
        if (count($ids) < 2) {
            throw new InvalidArgumentException('Pilih sekurang-kurangnya 2 baris untuk dilonggok.');
        }

        $coaId = (int) ($data['coa_id'] ?? 0);
        if ($coaId <= 0) {
            throw new InvalidArgumentException('Sila pilih kod akaun (COA).');
        }

        return DB::transaction(function () use ($ids, $data, $coaId) {
            // Kunci semua baris DALAM transaksi — halang klik berganda / longgok bertindih.
            $lines = BankStatementLine::whereIn('id', $ids)->lockForUpdate()->get();
            if ($lines->count() !== count($ids)) {
                throw new InvalidArgumentException('Sebahagian baris tidak dijumpai.');
            }

            $masuk = null;
            $bankId = null;
            $total = 0.0;
            foreach ($lines as $line) {
                $this->pastikanBolehRekod($line); // batch SEDIA masjid semasa + UNMATCHED
                $sisi = (float) $line->kredit > 0;
                if ($masuk === null) {
                    $masuk = $sisi;
                } elseif ($masuk !== $sisi) {
                    throw new InvalidArgumentException('Semua baris dipilih mesti SAMA jenis (semua masuk ATAU semua keluar).');
                }
                if ($bankId === null) {
                    $bankId = (int) $line->bank_account_id;
                } elseif ($bankId !== (int) $line->bank_account_id) {
                    throw new InvalidArgumentException('Baris dari akaun bank berbeza tidak boleh dilonggok bersama.');
                }
                $total += $masuk ? (float) $line->kredit : (float) $line->debit;
            }

            // Kuatkuasa keluarga COA di PELAYAN (sama seperti rekodBaris).
            $kod = (string) \App\Models\Coa::whereKey($coaId)->value('kod');
            $sah = $masuk
                ? (str_starts_with($kod, '400-') || str_starts_with($kod, '450-') || str_starts_with($kod, '300-04'))
                : (str_starts_with($kod, '600-') || str_starts_with($kod, '650-'));
            if (!$sah) {
                throw new InvalidArgumentException($masuk
                    ? 'Wang masuk mesti direkod ke kod hasil (400/450) atau tabung (300-04xxx).'
                    : 'Wang keluar mesti direkod ke kod belanja (600/650).');
            }

            $tarikh = $data['tarikh'] ?? now()->format('Y-m-d');
            $jumlah = number_format($total, 2, '.', '');
            $deskripsi = $data['deskripsi'] ?? ($masuk ? 'Longgokan kutipan (QR/infaq)' : 'Longgokan bayaran');

            if ($masuk) {
                $rekod = $this->kutipan->create([
                    'jenis' => 'BIASA', 'tarikh' => $tarikh, 'coa_id' => $coaId,
                    'kaedah' => 'BANK_TRANSFER_QR', 'jumlah' => $jumlah, 'auto_resit' => true,
                    'nama_pemberi' => $data['penerima'] ?? null, 'bank_account_id' => $bankId,
                    'deskripsi' => $deskripsi,
                ]);
            } else {
                $rekod = $this->pembayaran->createBayaran([
                    'tar_lulus' => $tarikh, 'coa_id' => $coaId, 'jumlah' => $jumlah,
                    'cara_bayar' => 'EFT', 'auto_baucer' => true, 'pemohon' => $data['penerima'] ?? null,
                    'deskripsi' => $deskripsi, 'bank_account_id' => $bankId,
                ]);
            }

            // Tandakan SEMUA baris dipilih MATCHED ke voucher lump-sum ini.
            BankStatementLine::whereIn('id', $ids)
                ->update(['status' => 'MATCHED', 'matched_voucher_id' => $rekod->voucher_id]);

            $this->audit->log('UPDATE', 'bank_statement_line', null, [
                'lump_sum' => true, 'bil_baris' => count($ids), 'jenis' => $masuk ? 'KUTIPAN' : 'BAYARAN',
                'recno' => $rekod->id, 'voucher' => $rekod->voucher_id, 'jumlah' => $jumlah,
            ], null);

            return ['jenis' => $masuk ? 'KUTIPAN' : 'BAYARAN', 'recno' => $rekod->id,
                'voucher_id' => $rekod->voucher_id, 'bil' => count($ids), 'jumlah' => $jumlah];
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
