<?php

namespace App\Ai;

use App\Ai\Contracts\VisionExtractorInterface;
use App\Ai\Dialects\AnthropicDialect;
use App\Ai\Dialects\GeminiDialect;
use App\Ai\Dialects\OpenAiDialect;
use App\Models\AiProviderConfig;
use App\Services\Security\SecretVaultService;
use Illuminate\Support\Facades\DB;

/**
 * Kilang klien AI multi-provider. Pilih konfigurasi aktif masjid
 * (utamakan is_default), ambil kunci API dari vault (TIDAK plaintext dalam
 * jadual), dan pulangkan extractor mengikut dialect.
 */
class AiClientFactory
{
    /**
     * Prompt TETAP — ikut rangka examples/telegram-ai-webhook.js.
     * Senarai COA tempatan masjid dilampirkan oleh buildPrompt().
     */
    public const PROMPT_ASAS = <<<'PROMPT'
Anda pembantu kewangan masjid. Ekstrak data dari resit/bil/slip bank ini.
Pulangkan JSON SAHAJA (tiada teks lain) dengan kunci:
{"doc_type":"RESIT_BANK|BIL|INVOIS|SLIP_BANK|RESIT_KUTIPAN|LAIN","tarikh":"YYYY-MM-DD","penerima":"",
"jumlah":0.00,"no_rujukan":"","no_akaun":"","bank":"","kaedah":"EFT|QR|CEK|TUNAI","cadangan_coa":"cth 600-06000",
"keterangan":"","confidence":0}
confidence ialah keyakinan anda 0-100. Jika maklumat tiada, biarkan kosong.
PROMPT;

    public function __construct(private SecretVaultService $vault)
    {
    }

    /**
     * @return array{0: VisionExtractorInterface, 1: AiProviderConfig}
     *
     * @throws AiException jika tiada konfigurasi/kunci
     */
    public function forMasjid(int $masjidId): array
    {
        $config = AiProviderConfig::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('is_active', 1)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if (!$config) {
            throw new AiException(
                'Tiada konfigurasi AI aktif untuk masjid ini. Sila tambah provider di Tetapan AI & Telegram.'
            );
        }

        $apiKey = $this->vault->get($config->api_key_ref);
        if ($apiKey === null || $apiKey === '') {
            throw new AiException(
                "Kunci API untuk provider {$config->provider} tidak ditemui dalam vault (ref: {$config->api_key_ref})."
            );
        }

        $extractor = match ($config->dialect) {
            'openai' => new OpenAiDialect($apiKey, $config->model, $config->base_url),
            'anthropic' => new AnthropicDialect($apiKey, $config->model, $config->base_url),
            'gemini' => new GeminiDialect($apiKey, $config->model, $config->base_url),
            default => throw new AiException("Dialect '{$config->dialect}' tidak disokong."),
        };

        return [$extractor, $config];
    }

    /** Prompt penuh = arahan asas + panduan COA tempatan masjid (≤30 mapping). */
    public function buildPrompt(int $masjidId): string
    {
        $mappings = DB::table('coa_local_mapping as m')
            ->join('coa as c', 'c.id', '=', 'm.coa_id')
            ->where('m.masjid_id', $masjidId)
            ->orderBy('m.local_label')
            ->limit(30)
            ->get(['m.local_label', 'c.kod']);

        if ($mappings->isEmpty()) {
            return self::PROMPT_ASAS;
        }

        $senarai = $mappings
            ->map(fn ($m) => "- {$m->kod} = {$m->local_label}")
            ->implode("\n");

        return self::PROMPT_ASAS."\n\nPanduan cadangan_coa (kod = kegunaan biasa masjid ini):\n".$senarai;
    }
}
