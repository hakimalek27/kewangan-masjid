<?php

namespace App\Jobs;

use App\Ai\AiClientFactory;
use App\Ai\AiException;
use App\Models\BankStatementLine;
use App\Models\PenyataSemakan;
use App\Services\Ai\CoaCadanganService;
use App\Services\Ai\KuotaPenyataService;
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
    public int $timeout = 300; // panggilan AI penyata sehingga 120s + insert baris

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
    ): void {
        $batch = PenyataSemakan::withoutMasjidScope()->find($this->batchId);
        if (!$batch || !in_array($batch->status, ['UPLOADED', 'AI_PROCESSING'], true)) {
            return; // idempotent
        }
        if ($batch->baris()->exists()) {
            $batch->update(['status' => 'SEDIA']);

            return; // baris sudah dimasukkan — jangan duplikasi
        }

        // Pertahanan job-side: toggle global mungkin dimatikan selepas dispatch.
        if (!$kuota->globallyEnabled()) {
            $batch->update(['status' => 'GAGAL', 'error_text' => 'Ciri Semak Penyata dimatikan oleh pentadbir sistem.']);

            return;
        }

        $batch->update(['status' => 'AI_PROCESSING']);
        $masjidId = (int) $batch->masjid_id;

        try {
            [$extractor, $config] = $factory->forPusat();

            $bytes = Storage::disk('local')->get($batch->file_path);
            if ($bytes === null) {
                throw new AiException('Fail penyata tidak ditemui: '.$batch->file_path);
            }

            $hasil = $extractor->extractStatement($bytes, $batch->mime, $factory->buildPromptPenyata($masjidId));

            if (empty($hasil->lines)) {
                throw new AiException('AI tidak menemui sebarang baris transaksi dalam penyata ini.');
            }

            $bilBaris = 0;
            DB::transaction(function () use ($hasil, $batch, $cadangan, $masjidId, &$bilBaris) {
                foreach ($hasil->lines as $ln) {
                    $tarikh = $ln->tarikh ?? now()->format('Y-m-d');
                    $deskripsi = mb_substr(trim((string) $ln->deskripsi), 0, 255);

                    // Dedup 5-medan sama seperti ReconciliationService::importCsv (E8).
                    $wujud = BankStatementLine::where('bank_account_id', $batch->bank_account_id)
                        ->where('tarikh', $tarikh)
                        ->where('debit', $ln->debit)
                        ->where('kredit', $ln->kredit)
                        ->where('deskripsi', $deskripsi)
                        ->exists();
                    if ($wujud) {
                        continue;
                    }

                    $masuk = (float) $ln->kredit > 0;
                    $jenis = $ln->cadanganJenis ?? ($masuk ? 'KUTIPAN' : 'BAYARAN');

                    $coaId = $cadangan->petakan($masjidId, $ln->cadanganCoa)
                        ?? ($jenis === 'KUTIPAN'
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
                        'ai_confidence' => $ln->confidence,
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
                'tokens_used' => $hasil->tokensUsed,
                'cost_usd' => $hasil->tokensUsed && $kosPer1k > 0
                    ? round($hasil->tokensUsed / 1000 * $kosPer1k, 4) : null,
                'bil_baris' => $bilBaris,
                'bil_auto_padan' => $dipadan,
                'error_text' => null,
            ]);

            $audit->log('UPDATE', 'penyata_semakan', null, [
                'batch' => $batch->id, 'bil_baris' => $bilBaris, 'auto_padan' => $dipadan,
                'tokens' => $hasil->tokensUsed,
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

    /** Dipanggil Laravel HANYA selepas semua percubaan habis — GAGAL membebaskan kuota. */
    public function failed(Throwable $e): void
    {
        PenyataSemakan::withoutMasjidScope()
            ->whereKey($this->batchId)
            ->whereIn('status', ['UPLOADED', 'AI_PROCESSING'])
            ->update([
                'status' => 'GAGAL',
                'error_text' => mb_substr($e->getMessage(), 0, 1000),
            ]);
    }
}
