<?php

namespace App\Jobs;

use App\Ai\AiClientFactory;
use App\Ai\AiException;
use App\Models\AiCallLog;
use App\Models\AiExtraction;
use App\Models\Coa;
use App\Models\CoaLocalMapping;
use App\Models\DocInbox;
use App\Models\TgBotConfig;
use App\Models\TxnDraft;
use App\Services\Ai\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Langkah 2 pipeline: hantar fail ke AI Vision → simpan ekstrakan + log →
 * cipta TxnDraft PENDING_REVIEW. AI TIDAK PERNAH pos jurnal — pengesahan
 * bendahari di web (DraftService::confirm) sahaja yang merekod transaksi.
 * Idempotent: hanya proses inbox DOWNLOADED/AI_PROCESSING tanpa draf sedia ada.
 */
class CallAiExtraction implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 240; // panggilan AI vision sehingga 120s

    public function __construct(public int $docInboxId)
    {
        $this->onQueue('ai');
    }

    public function handle(AiClientFactory $factory): void
    {
        $inbox = DocInbox::withoutMasjidScope()->find($this->docInboxId);
        if (!$inbox || !in_array($inbox->status, ['DOWNLOADED', 'AI_PROCESSING'], true)) {
            return; // idempotent — status lain bermaksud sudah/tidak perlu diproses
        }
        if (TxnDraft::withoutMasjidScope()->where('inbox_id', $inbox->id)->exists()) {
            $inbox->update(['status' => 'DRAFT_CREATED']);

            return; // draf sudah wujud — jangan duplikasi
        }

        $inbox->update(['status' => 'AI_PROCESSING']);
        $telegram = TelegramService::forMasjid((int) $inbox->masjid_id);
        $mula = microtime(true);
        $provider = null;

        try {
            [$extractor, $config] = $factory->forMasjid((int) $inbox->masjid_id);
            $provider = $config;

            $bytes = Storage::disk('local')->get($inbox->file_path);
            if ($bytes === null) {
                throw new AiException('Fail inbox tidak ditemui: '.$inbox->file_path);
            }

            $mime = $inbox->file_type === 'PDF' ? 'application/pdf' : 'image/jpeg';
            $hasil = $extractor->extract($bytes, $mime, $factory->buildPrompt((int) $inbox->masjid_id));

            AiCallLog::create([
                'inbox_id' => $inbox->id,
                'provider' => $config->provider,
                'model' => $config->model,
                'http_status' => 200,
                'latency_ms' => (int) round((microtime(true) - $mula) * 1000),
                'ok' => true,
            ]);

            $rawArr = json_decode($hasil->rawJson, true);

            $extraction = AiExtraction::create([
                'inbox_id' => $inbox->id,
                'provider' => $config->provider,
                'model' => $config->model,
                'doc_type' => $this->docTypeSah($hasil->doc_type),
                'ex_tarikh' => $hasil->tarikh,
                'ex_penerima' => mb_substr((string) $hasil->penerima, 0, 200) ?: null,
                'ex_jumlah' => $hasil->jumlah,
                'ex_no_rujukan' => mb_substr((string) $hasil->no_rujukan, 0, 80) ?: null,
                'ex_no_akaun' => mb_substr((string) $hasil->no_akaun, 0, 60) ?: null,
                'ex_bank' => mb_substr((string) $hasil->bank, 0, 120) ?: null,
                'ex_kaedah' => mb_substr((string) $hasil->kaedah, 0, 40) ?: null,
                'ex_cadangan_coa' => mb_substr((string) $hasil->cadangan_coa, 0, 15) ?: null,
                'ex_keterangan' => mb_substr((string) $hasil->keterangan, 0, 500) ?: null,
                'raw_json' => is_array($rawArr) ? $rawArr : ['raw' => $hasil->rawJson],
                'confidence' => $hasil->confidence,
                'tokens_used' => $hasil->tokensUsed,
                'cost_usd' => $hasil->costUsd,
            ]);

            $draf = $this->ciptaDraf($inbox, $extraction, $hasil->cadangan_coa);

            $inbox->update(['status' => 'DRAFT_CREATED']);

            // Webhook draft.created (best-effort; job berjalan di luar konteks request
            // jadi masjid_id dihantar eksplisit)
            try {
                app(\App\Services\Api\WebhookDispatcher::class)->dispatch('draft.created', [
                    'draft_id' => $draf->id, 'jenis' => $draf->jenis,
                    'jumlah' => (string) $draf->jumlah, 'confidence' => (int) $extraction->confidence,
                ], (int) $inbox->masjid_id);
            } catch (Throwable $wh) {
                report($wh);
            }

            // Notifikasi Telegram adalah BEST-EFFORT — kegagalan menghantar mesej
            // TIDAK boleh memusnahkan draf yang sudah berjaya dicipta (elak status
            // tertimpa AI_FAILED & risiko rekod berganda). Diasingkan dari try utama.
            try {
                $telegram->sendMessage($inbox->tg_chat_id, $this->mesejRingkasan($draf, $extraction));
            } catch (Throwable $tg) {
                report($tg);
            }
        } catch (Throwable $e) {
            AiCallLog::create([
                'inbox_id' => $inbox->id,
                'provider' => $provider->provider ?? 'TIADA',
                'model' => $provider->model ?? null,
                'http_status' => $e instanceof AiException ? $e->httpStatus : null,
                'latency_ms' => (int) round((microtime(true) - $mula) * 1000),
                'ok' => false,
                'error_text' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            // Kekalkan status AI_PROCESSING semasa percubaan bukan-akhir supaya guard
            // handle() menerima retry queue (tries 3). Penandaan AI_FAILED muktamad +
            // notifikasi "rekod manual" dibuat dalam failed() (selepas semua tries habis).
            $inbox->update(['status' => 'AI_PROCESSING']);

            throw $e; // benarkan retry queue (tries 3, backoff 30s)
        }
    }

    /** Dipanggil Laravel HANYA selepas semua percubaan (tries) habis. */
    public function failed(Throwable $e): void
    {
        $inbox = DocInbox::withoutMasjidScope()->find($this->docInboxId);
        if (!$inbox) {
            return;
        }

        // Jika draf sudah wujud (cth ekstrakan berjaya tetapi langkah lain gagal),
        // jangan tanda gagal — draf sah masih boleh disahkan bendahari.
        if (TxnDraft::withoutMasjidScope()->where('inbox_id', $inbox->id)->exists()) {
            $inbox->update(['status' => 'DRAFT_CREATED']);

            return;
        }

        $inbox->update(['status' => 'AI_FAILED']);

        try {
            TelegramService::forMasjid((int) $inbox->masjid_id)->sendMessage(
                $inbox->tg_chat_id,
                '❌ Maaf, pemprosesan AI gagal untuk dokumen #'.$inbox->id.'. Sila rekod secara manual di sistem.'
            );
        } catch (Throwable $tg) {
            report($tg);
        }
    }

    private function ciptaDraf(DocInbox $inbox, AiExtraction $extraction, ?string $cadanganCoa): TxnDraft
    {
        $bot = TgBotConfig::withoutMasjidScope()
            ->where('masjid_id', $inbox->masjid_id)
            ->where('is_active', 1)
            ->first();

        $deskripsi = trim(implode(' | ', array_filter([
            $extraction->ex_keterangan,
            $inbox->caption,
        ])));

        return TxnDraft::create([
            'masjid_id' => $inbox->masjid_id,
            'inbox_id' => $inbox->id,
            'extraction_id' => $extraction->id,
            'jenis' => ($bot?->default_jenis === 'BAYARAN') ? 'BAYARAN' : 'KUTIPAN',
            'tarikh' => $extraction->ex_tarikh?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'coa_id' => $this->petakanCoa((int) $inbox->masjid_id, $cadanganCoa),
            'jumlah' => $extraction->ex_jumlah,
            'penerima' => $extraction->ex_penerima,
            'no_rujukan' => $extraction->ex_no_rujukan,
            'kaedah' => $extraction->ex_kaedah,
            'deskripsi' => mb_substr($deskripsi, 0, 500) ?: null,
            'status' => 'PENDING_REVIEW',
        ]);
    }

    /** Petakan cadangan AI → coa.id: kod sama ATAU label tempatan sepadan (case-insensitive). */
    private function petakanCoa(int $masjidId, ?string $cadangan): ?int
    {
        $cadangan = trim((string) $cadangan);
        if ($cadangan === '') {
            return null;
        }

        $coa = Coa::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('kod', $cadangan)
            ->value('id');
        if ($coa) {
            return (int) $coa;
        }

        $mapping = CoaLocalMapping::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->whereRaw('LOWER(local_label) = ?', [mb_strtolower($cadangan)])
            ->value('coa_id');

        return $mapping ? (int) $mapping : null;
    }

    private function mesejRingkasan(TxnDraft $draf, AiExtraction $extraction): string
    {
        $kategori = $draf->coa_id
            ? Coa::withoutMasjidScope()->where('id', $draf->coa_id)->value('kod')
            : ($extraction->ex_cadangan_coa ?: '(tiada)');

        $teks = "📝 Draf #{$draf->id} dicipta ({$draf->jenis})\n"
            ."Tarikh: {$draf->tarikh?->format('Y-m-d')}\n"
            .'Jumlah: RM '.number_format((float) ($draf->jumlah ?? 0), 2)."\n"
            .'Penerima: '.($draf->penerima ?: '(tiada)')."\n"
            ."Kategori (cadangan): {$kategori}\n"
            .'Keyakinan AI: '.(int) $extraction->confidence.'%';

        if ((float) $extraction->confidence < 80) {
            $teks .= "\n⚠️ Keyakinan AI rendah — sila semak dengan teliti.";
        }

        return $teks."\n👉 Sila SAHKAN di sistem.";
    }

    private function docTypeSah(?string $docType): ?string
    {
        $sah = ['RESIT_BANK', 'BIL', 'INVOIS', 'SLIP_BANK', 'RESIT_KUTIPAN', 'LAIN'];

        return in_array($docType, $sah, true) ? $docType : ($docType ? 'LAIN' : null);
    }
}
