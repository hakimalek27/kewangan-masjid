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

    // Pecahan token terkumpul (input/output) merentas semua panggilan AI batch ini —
    // untuk kira kos TEPAT ikut kadar berasingan (bukan pukul rata).
    private int $promptTokens = 0;
    private int $completionTokens = 0;

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

            [$lines, $totalTokens, $keadaan] = $this->ekstrakBaris($batch, $extractor, $pdf, $factory, $masjidId);

            // Dibatalkan pengguna semasa proses → BUANG hasil separa, tanda DIBATAL.
            if ($keadaan === 'DIBATAL') {
                $batch->update([
                    'status' => 'DIBATAL', 'batal_diminta' => false,
                    'tokens_used' => $totalTokens ?: null,
                    'prompt_tokens' => $this->promptTokens ?: null,
                    'completion_tokens' => $this->completionTokens ?: null,
                    'error_text' => 'Dibatalkan oleh pengguna semasa pemprosesan AI.',
                ]);
                $audit->log('UPDATE', 'penyata_semakan', null, [
                    'batch' => $batch->id, 'nota' => 'dibatalkan pengguna', 'tokens' => $totalTokens,
                ], $batch->id, masjidId: $masjidId);

                return;
            }

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
                        ?? $cadangan->cadangDariDeskripsi($masjidId, $deskripsi, $masuk)
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

            // Had kos dicapai → hasil DISIMPAN tetapi separa; amaran (bukan ralat).
            $amaran = $keadaan === 'HAD_KOS'
                ? 'Had kos AI dicapai — hanya '.((int) $batch->muka_siap).'/'.((int) $batch->muka_jumlah)
                    .' muka diproses. Sebahagian transaksi mungkin belum discan; naikkan had kos di Tetapan AI atau pisahkan fail.'
                : null;

            // Kos TEPAT: kadar input/output provider × token sebenar (usage). Jika
            // provider tiada kadar → pukul rata sp_kos_per_1k_usd × jumlah token.
            $kosPer1k = (float) Setting::get('sp_kos_per_1k_usd', '0', KuotaPenyataService::MASJID_GLOBAL);
            $kosInput = $config['kos_input_1k'] ?? null;
            $kosOutput = $config['kos_output_1k'] ?? null;
            // 1) Kadar input/output provider (TEPAT) → 2) kadar global pukul-rata →
            // 3) fallback config (anggaran) supaya kos TIDAK pernah null bila ada token.
            $blended = $kosPer1k > 0 ? $kosPer1k : (float) config('spkm.penyata_kos_blended_lalai', 0.006);
            $cost = ($kosInput !== null && $kosOutput !== null)
                ? round($this->promptTokens / 1000 * (float) $kosInput + $this->completionTokens / 1000 * (float) $kosOutput, 4)
                : ($totalTokens > 0 ? round($totalTokens / 1000 * $blended, 4) : null);

            $batch->update([
                'status' => 'SEDIA',
                'provider' => $config['provider'],
                'model' => $config['model'],
                'tokens_used' => $totalTokens,
                'prompt_tokens' => $this->promptTokens ?: null,
                'completion_tokens' => $this->completionTokens ?: null,
                'cost_usd' => $cost,
                'bil_baris' => $bilBaris,
                'bil_auto_padan' => $dipadan,
                'error_text' => $amaran,
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
     * Ekstrak baris + jumlah token. Pilih kaedah terbaik ikut jenis fail:
     *  - Imej tunggal / PDF tanpa Poppler   → satu panggilan (fallback).
     *  - PDF DIGITAL (teks terbenam)        → baca TEKS terus, gabung beberapa muka
     *    satu panggilan. Pantas (saat) + tepat (tiada ralat OCR).
     *  - PDF IMBASAN (image-only)           → render setiap muka ke imej, OCR
     *    satu-satu (model vision hanya baca muka pertama jika PDF penuh dihantar).
     *
     * @return array{0: array, 1: int, 2: string}  [$lines, $totalTokens, $keadaan]
     *   $keadaan: 'OK' | 'DIBATAL' (dibatalkan pengguna) | 'HAD_KOS' (had kos dicapai)
     */
    private function ekstrakBaris(PenyataSemakan $batch, $extractor, PdfRenderService $pdf, $factory, int $masjidId): array
    {
        // Imej tunggal, ATAU PDF tetapi Poppler tiada → satu panggilan.
        if ($batch->mime !== 'application/pdf' || !$pdf->tersedia()) {
            if ($this->dibatalkan($batch->id)) {
                return [[], 0, 'DIBATAL'];
            }
            $bytes = Storage::disk('local')->get($batch->file_path);
            if ($bytes === null) {
                throw new AiException('Fail penyata tidak ditemui: '.$batch->file_path);
            }
            $batch->update([
                'kaedah' => $batch->mime === 'application/pdf' ? 'PDF' : 'IMEJ',
                'muka_jumlah' => 1, 'muka_siap' => 0,
            ]);
            $r = $extractor->extractStatement($bytes, $batch->mime, $factory->buildPromptPenyata($masjidId));
            $total = $this->kiraToken($r);
            $batch->update(['muka_siap' => 1]);

            return [$r->lines, $total, 'OK'];
        }

        $absPdf = Storage::disk('local')->path($batch->file_path);

        // PDF DIGITAL? Teks terbenam bermakna pada ≥60% muka → baca teks terus.
        $teksMuka = $pdf->ekstrakTeks($absPdf);
        $berteks = 0;
        foreach ($teksMuka as $t) {
            if (mb_strlen(trim($t)) >= 40) {
                $berteks++;
            }
        }
        $digital = !empty($teksMuka) && $berteks >= max(1, (int) ceil(count($teksMuka) * 0.6));

        if ($digital) {
            // PARSE DETERMINISTIK dahulu (format jadual dikenali) — PERCUMA, pantas,
            // 100% konsisten & lengkap. AI (LLM) untuk transkrip jadual besar =
            // mahal + tidak konsisten + tertinggal baris. AI hanya fallback.
            $parse = app(\App\Services\Ai\PenyataDigitalParser::class)->cubaParse(implode("\n", $teksMuka));
            if ($parse !== null) {
                $batch->update([
                    'kaedah' => 'PARSE',
                    'muka_jumlah' => count($teksMuka), 'muka_siap' => count($teksMuka),
                    'penyata_jum_debit' => $parse['grand_debit'],
                    'penyata_jum_kredit' => $parse['grand_credit'],
                ]);

                return [$parse['lines'], 0, 'OK'];
            }

            // Format digital tak dikenali parser (cth bank tukar susun lajur) → guna AI.
            // Log untuk admin supaya parser format ini boleh ditambah kemudian.
            report(new \RuntimeException('Semak Penyata: PDF digital format tidak dikenali parser deterministik — beralih ke AI (batch #'.$batch->id.').'));

            return $this->ekstrakDigital($batch, $extractor, $factory, $masjidId, $teksMuka);
        }

        return $this->ekstrakScan($batch, $extractor, $pdf, $factory, $masjidId, $absPdf);
    }

    /**
     * PDF DIGITAL — teks per muka dibaca terus (pdftotext), digabung beberapa muka
     * satu panggilan AI. Kemas kini muka_siap untuk bar progres.
     *
     * @return array{0: array, 1: int, 2: string}
     */
    private function ekstrakDigital(PenyataSemakan $batch, $extractor, $factory, int $masjidId, array $teksMuka): array
    {
        $prompt = $factory->buildPromptPenyata($masjidId, teks: true);
        $muka = array_values(array_filter(array_map('rtrim', $teksMuka), fn ($t) => trim($t) !== ''));
        $batch->update(['kaedah' => 'DIGITAL', 'muka_jumlah' => count($muka), 'muka_siap' => 0]);

        $per = max(1, (int) config('spkm.penyata_text_pages_per_call', 8));
        $had = $this->hadKosUsd();
        $kosPer1k = $this->kosPer1k();
        $lines = [];
        $totalTokens = 0;
        $siap = 0;
        $keadaan = 'OK';

        foreach (array_chunk($muka, $per) as $kelompok) {
            if ($this->dibatalkan($batch->id)) {
                $keadaan = 'DIBATAL';
                break;
            }
            $teks = '';
            foreach ($kelompok as $t) {
                $teks .= "\n----- MUKA -----\n".$t;
            }
            try {
                $r = $extractor->extractStatement($teks, 'text/plain', $prompt);
                $lines = array_merge($lines, $r->lines);
                $totalTokens += $this->kiraToken($r);
            } catch (\Throwable $e) {
                report($e); // langkau kelompok gagal, teruskan
            }
            $siap += count($kelompok);
            $batch->update(['muka_siap' => $siap]);
            if ($this->melebihiHad($totalTokens, $kosPer1k, $had)) {
                $keadaan = 'HAD_KOS';
                break;
            }
        }

        return [$lines, $totalTokens, $keadaan];
    }

    /**
     * PDF IMBASAN — render setiap muka ke imej (Poppler), OCR satu-satu, gabung.
     * Tahan-ralat per-muka: kegagalan satu muka TIDAK membuang muka lain.
     *
     * @return array{0: array, 1: int, 2: string}
     */
    private function ekstrakScan(PenyataSemakan $batch, $extractor, PdfRenderService $pdf, $factory, int $masjidId, string $absPdf): array
    {
        $prompt = $factory->buildPromptPenyata($masjidId);
        $tmpDir = storage_path('app/penyata-ai-tmp'.DIRECTORY_SEPARATOR.$batch->id);
        $had = $this->hadKosUsd();
        $kosPer1k = $this->kosPer1k();
        // OCR SELARI (superadmin toggle): proses beberapa muka serentak (Http::pool).
        $selari = Setting::isOn('sp_ocr_selari', KuotaPenyataService::MASJID_GLOBAL);
        $chunkSize = $selari ? max(1, (int) config('spkm.ocr_selari_bil', 5)) : 1;
        $lines = [];
        $totalTokens = 0;
        $keadaan = 'OK';

        try {
            $imej = $pdf->renderKeImej($absPdf, $tmpDir);
            if (empty($imej)) {
                throw new AiException('Tiada muka dapat dirender daripada PDF.');
            }
            $batch->update(['kaedah' => 'SCAN', 'muka_jumlah' => count($imej), 'muka_siap' => 0]);
            $siap = 0;

            foreach (array_chunk($imej, $chunkSize) as $kelompok) {
                if ($this->dibatalkan($batch->id)) {
                    $keadaan = 'DIBATAL';
                    break;
                }

                $bytesArr = [];
                foreach ($kelompok as $img) {
                    $b = @file_get_contents($img);
                    if ($b !== false) {
                        $bytesArr[] = $b;
                    }
                }

                if ($selari && count($bytesArr) > 1 && method_exists($extractor, 'extractStatementBatch')) {
                    // Kelompok serentak.
                    try {
                        foreach ($extractor->extractStatementBatch($bytesArr, 'image/jpeg', $prompt) as $r) {
                            if ($r === null) {
                                continue; // muka gagal — langkau
                            }
                            $lines = array_merge($lines, $r->lines);
                            $totalTokens += $this->kiraToken($r);
                        }
                    } catch (\Throwable $e) {
                        report($e);
                    }
                } else {
                    // Berturutan.
                    foreach ($bytesArr as $b) {
                        try {
                            $r = $extractor->extractStatement($b, 'image/jpeg', $prompt);
                            $lines = array_merge($lines, $r->lines);
                            $totalTokens += $this->kiraToken($r);
                        } catch (\Throwable $e) {
                            report($e); // log muka gagal, teruskan muka seterusnya
                        }
                    }
                }

                $siap += count($kelompok);
                $batch->update(['muka_siap' => $siap]);
                if ($this->melebihiHad($totalTokens, $kosPer1k, $had)) {
                    $keadaan = 'HAD_KOS';
                    break;
                }
            }
        } finally {
            // Buang imej sementara (elak longgokan cakera).
            foreach (glob($tmpDir.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmpDir);
        }

        return [$lines, $totalTokens, $keadaan];
    }

    /** Kumpul pecahan token satu respons; pulang jumlah token (untuk had kos). */
    private function kiraToken($r): int
    {
        $this->promptTokens += (int) $r->promptTokens;
        $this->completionTokens += (int) $r->completionTokens;

        return (int) $r->tokensUsed;
    }

    /** Pengguna minta batal? (dibaca segar setiap kelompok — job berhenti awal). */
    private function dibatalkan(int $batchId): bool
    {
        return (bool) PenyataSemakan::withoutMasjidScope()->whereKey($batchId)->value('batal_diminta');
    }

    /** Had kos USD setiap permintaan (0 = tiada had). */
    private function hadKosUsd(): float
    {
        return (float) Setting::get('sp_had_usd_permintaan',
            (string) config('spkm.penyata_had_usd_lalai'), KuotaPenyataService::MASJID_GLOBAL);
    }

    /** Kos USD setiap 1k token (untuk pemutus had + paparan kos). */
    private function kosPer1k(): float
    {
        return (float) Setting::get('sp_kos_per_1k_usd', '0', KuotaPenyataService::MASJID_GLOBAL);
    }

    /** Anggaran kos setakat ini melebihi had? (perlu had>0 & harga token diketahui). */
    private function melebihiHad(int $totalTokens, float $kosPer1k, float $had): bool
    {
        return $had > 0 && $kosPer1k > 0 && ($totalTokens / 1000 * $kosPer1k) >= $had;
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
