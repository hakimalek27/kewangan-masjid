<?php

namespace App\Jobs;

use App\Ai\AiClientFactory;
use App\Ai\AiException;
use App\Models\BankStatementLine;
use App\Models\PenyataSemakan;
use App\Services\Ai\CoaCadanganService;
use App\Services\Ai\KuotaPenyataService;
use App\Services\Ai\PdfRenderService;
use App\Services\Lanjutan\ReconciliationService;
use App\Services\Security\AuditTrailService;
use App\Support\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * "Semak Penyata (AI)" — proses satu batch penyata bank melalui AI PUSAT:
 * ekstrak setiap baris → masukkan bank_statement_line (UNMATCHED + cadangan
 * COA) → padanAuto lawan lejar. AI TIDAK PERNAH pos jurnal — bendahari sahkan
 * baris satu demi satu di halaman Semak Penyata.
 * Idempotent: hanya proses batch UPLOADED/AI_PROCESSING tanpa baris sedia ada.
 */
class ProsesPenyataAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 60;
    // PDF imbasan berbilang-muka diproses satu muka satu panggilan AI (≤120s setiap
    // satu) → penyata 70+ muka boleh ambil beberapa minit. Had luas untuk pipeline.
    public int $timeout = 1800;

    public function __construct(public int $batchId)
    {
        $this->onQueue('ai');
    }

    public function handle(
        AiClientFactory $factory,
        CoaCadanganService $cadangan,
        ReconciliationService $recon,
        KuotaPenyataService $kuota,
        AuditTrailService $audit,
        PdfRenderService $pdf,
    ): void {
        // Pertahanan: panggilan AI penyata boleh ambil sehingga 120s. Bila queue
        // berjalan 'sync' (dev, tiada worker), job ini dilaksana dalam request web
        // yang terhad kepada max_execution_time=30 → fatal. Naikkan had di sini supaya
        // selari dgn $timeout=300 job (worker CLI biasanya sudah tiada had).
        @set_time_limit($this->timeout);

        $batch = PenyataSemakan::withoutMasjidScope()->find($this->batchId);
        if (!$batch || !in_array($batch->status, ['UPLOADED', 'AI_PROCESSING'], true)) {
            return; // idempotent
        }
        if ($batch->baris()->exists()) {
            // Retry selepas insert komit tetapi langkah hilir gagal — lengkapkan
            // langkah hilir (padanAuto + kiraan), jangan masukkan baris semula.
            $dipadan = $recon->padanAuto((int) $batch->bank_account_id, (int) $batch->masjid_id);
            $batch->update([
                'status' => 'SEDIA',
                'bil_baris' => $batch->baris()->count(),
                'bil_auto_padan' => $batch->baris()->where('status', 'MATCHED')->count(),
            ]);

            return;
        }

        // Pertahanan job-side: toggle global mungkin dimatikan selepas dispatch.
        if (!$kuota->globallyEnabled()) {
            $batch->update(['status' => 'GAGAL', 'error_text' => 'Ciri Semak Penyata dimatikan oleh pentadbir sistem.']);

            return;
        }

        $batch->update(['status' => 'AI_PROCESSING']);
        $masjidId = (int) $batch->masjid_id;

        try {
            // Guna provider yang bendahari PILIH untuk batch ini (banding OCR);
            // 0 → forPusat() jatuh ke default/legasi.
            [$extractor, $config] = $factory->forPusat((int) $batch->sp_provider_id);

            // Rekod provider sebenar yang digunakan (label untuk paparan/banding).
            $batch->update(['provider_label' => ($config['provider'] ?? 'OPENAI').' ('.($config['model'] ?? '').')']);

            $prompt = $factory->buildPromptPenyata($masjidId);
            [$lines, $totalTokens] = $this->ekstrakBaris($batch, $extractor, $pdf, $prompt);

            if (empty($lines)) {
                throw new AiException('AI tidak menemui sebarang baris transaksi dalam penyata ini.');
            }

            // Dedup merentas import LAMA sahaja (CSV / batch bertindih) — duplikat
            // SAH dalam SATU penyata (cth. dua derma QR RM10 hari sama) mesti
            // dikekalkan. Pra-kira bilangan sedia ada di DB per kunci 5-medan;
            // salinan ke-N dalam respons hanya dilangkau jika DB sudah ada ≥ N.
            $kunci = fn (string $tarikh, string $debit, string $kredit, string $deskripsi) => $tarikh.'|'.$debit.'|'.$kredit.'|'.$deskripsi;
            $sediaAda = [];
            $dilihat = [];
            $bilBaris = 0;
            $bilLangkau = 0;

            DB::transaction(function () use ($lines, $batch, $cadangan, $masjidId, $kunci, &$sediaAda, &$dilihat, &$bilBaris, &$bilLangkau) {
                foreach ($lines as $ln) {
                    // Tarikh tak sah daripada AI: JANGAN reka senyap — guna tarikh
                    // hari ini TETAPI tanda keyakinan 0 + label supaya bendahari perasan.
                    $tarikhSah = $ln->tarikh !== null;
                    $tarikh = $ln->tarikh ?? now()->format('Y-m-d');
                    $deskripsi = mb_substr(trim((string) $ln->deskripsi), 0, 255);
                    if (!$tarikhSah) {
                        $deskripsi = mb_substr('[?tarikh] '.$deskripsi, 0, 255);
                    }

                    $k = $kunci($tarikh, $ln->debit, $ln->kredit, $deskripsi);
                    if (!array_key_exists($k, $sediaAda)) {
                        $sediaAda[$k] = BankStatementLine::where('bank_account_id', $batch->bank_account_id)
                            ->where('tarikh', $tarikh)
                            ->where('debit', $ln->debit)
                            ->where('kredit', $ln->kredit)
                            ->where('deskripsi', $deskripsi)
                            ->where(fn ($q) => $q->whereNull('batch_id')->orWhere('batch_id', '!=', $batch->id))
                            ->count();
                    }
                    $dilihat[$k] = ($dilihat[$k] ?? 0) + 1;
                    if ($dilihat[$k] <= $sediaAda[$k]) {
                        $bilLangkau++;

                        continue; // sudah wujud dari import terdahulu
                    }

                    // Jenis SENTIASA ikut tanda amaun (teks AI boleh bercanggah);
                    // cadangan AI hanya digunakan untuk pilihan COA.
                    $masuk = (float) $ln->kredit > 0;
                    $jenis = $masuk ? 'KUTIPAN' : 'BAYARAN';

                    $coaId = $cadangan->petakan($masjidId, $ln->cadanganCoa)
                        ?? ($masuk
                            ? $cadangan->fallbackKutipan($masjidId)
                            : $cadangan->fallbackBayaran($masjidId));

                    BankStatementLine::create([
                        'bank_account_id' => $batch->bank_account_id,
                        'tarikh' => $tarikh,
                        'deskripsi' => $deskripsi,
                        'debit' => $ln->debit,
                        'kredit' => $ln->kredit,
                        'baki' => $ln->baki,
                        'status' => 'UNMATCHED',
                        'batch_id' => $batch->id,
                        'cadangan_jenis' => $jenis,
                        'cadangan_coa_id' => $coaId,
                        'ai_confidence' => $tarikhSah ? $ln->confidence : 0,
                    ]);
                    $bilBaris++;
                }
            });

            // Padanan auto lawan lejar (masjid_id EKSPLISIT — job tiada konteks request).
            $dipadan = $bilBaris > 0 ? $recon->padanAuto((int) $batch->bank_account_id, $masjidId) : 0;

            $kosPer1k = (float) Setting::get('sp_kos_per_1k_usd', '0', KuotaPenyataService::MASJID_GLOBAL);
            $batch->update([
                'status' => 'SEDIA',
                'provider' => $config['provider'],
                'model' => $config['model'],
                'tokens_used' => $totalTokens,
                'cost_usd' => $totalTokens && $kosPer1k > 0
                    ? round($totalTokens / 1000 * $kosPer1k, 4) : null,
                'bil_baris' => $bilBaris,
                'bil_auto_padan' => $dipadan,
                'error_text' => null,
            ]);

            $audit->log('UPDATE', 'penyata_semakan', null, [
                'batch' => $batch->id, 'bil_baris' => $bilBaris, 'auto_padan' => $dipadan,
                'bil_langkau_duplikat' => $bilLangkau, 'tokens' => $totalTokens,
            ], $batch->id, masjidId: $masjidId);

            // Webhook best-effort (kegagalan tidak memusnahkan batch yang siap).
            try {
                app(\App\Services\Api\WebhookDispatcher::class)->dispatch('statement.ready', [
                    'batch_id' => $batch->id, 'bil_baris' => $bilBaris, 'bil_auto_padan' => $dipadan,
                ], $masjidId);
            } catch (Throwable $wh) {
                report($wh);
            }
        } catch (Throwable $e) {
            // Kekal AI_PROCESSING semasa percubaan bukan-akhir → guard handle() terima
            // retry queue; penandaan GAGAL muktamad dibuat dalam failed().
            $batch->update(['status' => 'AI_PROCESSING']);

            throw $e;
        }
    }

    /**
     * Ekstrak baris + jumlah token. PDF IMBASAN berbilang-muka → pecah kepada imej
     * setiap muka (Poppler) dan OCR SATU MUKA satu panggilan supaya SEMUA transaksi
     * dibaca (model vision hanya baca muka pertama bila PDF penuh dihantar terus).
     * Imej tunggal / PDF tanpa Poppler → satu panggilan (fallback).
     *
     * @return array{0: array, 1: int}  [$lines, $totalTokens]
     */
    private function ekstrakBaris(PenyataSemakan $batch, $extractor, PdfRenderService $pdf, string $prompt): array
    {
        // Imej tunggal, ATAU PDF tetapi Poppler tiada → satu panggilan.
        if ($batch->mime !== 'application/pdf' || !$pdf->tersedia()) {
            $bytes = Storage::disk('local')->get($batch->file_path);
            if ($bytes === null) {
                throw new AiException('Fail penyata tidak ditemui: '.$batch->file_path);
            }
            $r = $extractor->extractStatement($bytes, $batch->mime, $prompt);

            return [$r->lines, (int) $r->tokensUsed];
        }

        // PDF imbasan → render setiap muka ke imej, OCR satu-satu, gabung.
        $absPdf = Storage::disk('local')->path($batch->file_path);
        $tmpDir = storage_path('app/penyata-ai-tmp'.DIRECTORY_SEPARATOR.$batch->id);
        $lines = [];
        $totalTokens = 0;

        try {
            $imej = $pdf->renderKeImej($absPdf, $tmpDir);
            if (empty($imej)) {
                throw new AiException('Tiada muka dapat dirender daripada PDF.');
            }

            // Tahan-ralat per-muka: kegagalan satu muka (rate-limit/timeout) TIDAK
            // membuang muka lain — log & teruskan. GAGAL muktamad hanya jika SEMUA
            // muka gagal (baris kosong → dilempar oleh pemanggil).
            foreach ($imej as $img) {
                $b = @file_get_contents($img);
                if ($b === false) {
                    continue;
                }
                try {
                    $r = $extractor->extractStatement($b, 'image/jpeg', $prompt);
                    $lines = array_merge($lines, $r->lines);
                    $totalTokens += (int) $r->tokensUsed;
                } catch (\Throwable $e) {
                    report($e); // log muka gagal, teruskan muka seterusnya
                }
            }
        } finally {
            // Buang imej sementara (elak longgokan cakera).
            foreach (glob($tmpDir.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmpDir);
        }

        return [$lines, $totalTokens];
    }

    /** Dipanggil Laravel HANYA selepas semua percubaan habis — GAGAL membebaskan kuota. */
    public function failed(Throwable $e): void
    {
        report($e); // detail penuh ke log — error_text kepada tenant DITAPIS

        PenyataSemakan::withoutMasjidScope()
            ->whereKey($this->batchId)
            ->whereIn('status', ['UPLOADED', 'AI_PROCESSING'])
            ->update([
                'status' => 'GAGAL',
                'error_text' => $this->mesejGagalTenant($e),
            ]);
    }

    /**
     * Mesej gagal yang selamat dipaparkan kepada tenant — badan respons OpenAI
     * (boleh mengandungi maklumat akaun/organisasi kunci pusat) TIDAK didedahkan.
     */
    private function mesejGagalTenant(Throwable $e): string
    {
        if ($e instanceof AiException) {
            // Mesej buatan kita sendiri (BM, tiada badan respons) selamat;
            // mesej berprefix HTTP mengandungi badan respons provider — tapis.
            if (!str_contains($e->getMessage(), 'HTTP')) {
                return mb_substr($e->getMessage(), 0, 1000);
            }

            return 'Panggilan AI gagal'.($e->httpStatus ? ' (HTTP '.$e->httpStatus.')' : '')
                .'. Sila cuba muat naik semula; jika berterusan hubungi pentadbir sistem.';
        }

        return 'Pemprosesan gagal. Sila cuba muat naik semula; jika berterusan hubungi pentadbir sistem.';
    }
}
