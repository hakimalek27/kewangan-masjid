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

    /**
     * Prompt PENYATA BANK penuh (ciri "Semak Penyata (AI)") — mesti mengandungi
     * perkataan JSON (syarat response_format json_object) dan memulangkan
     * pembungkus {"lines":[...]} kerana json_object tak boleh array di akar.
     */
    public const PROMPT_PENYATA = <<<'PROMPT'
Anda pembantu kewangan masjid. Dokumen ini ialah PENYATA BANK penuh.
Ekstrak SETIAP baris transaksi, satu demi satu, dalam susunan tarikh penyata.
Pulangkan JSON SAHAJA (tiada teks lain) berbentuk:
{"lines":[{"tarikh":"YYYY-MM-DD","deskripsi":"","debit":0.00,"kredit":0.00,"baki":null,
"cadangan_jenis":"KUTIPAN|BAYARAN","cadangan_coa":"cth 400-01010","confidence":0}]}
Peraturan:
- Wang MASUK (kredit > 0) = KUTIPAN → cadangkan kod hasil 400/450 paling padan dengan deskripsi;
  jika deskripsi tidak bermakna/kosong, cadangkan kod infaq/sedekah.
- Wang KELUAR (debit > 0) = BAYARAN → cadangkan kod belanja 600/650 paling padan.
- Setiap baris hanya SATU sisi (debit ATAU kredit), bukan kedua-duanya.
- JANGAN cipta baris yang tiada dalam penyata. JANGAN langkau baris.
- confidence ialah keyakinan cadangan COA anda 0-100.
PROMPT;

    public function __construct(private SecretVaultService $vault)
    {
    }

    /**
     * Klien AI PUSAT untuk "Semak Penyata (AI)" — SATU kunci OpenAI dikawal
     * superadmin (app_setting masjid_id=0 + vault), dikongsi semua tenant.
     *
     * @return array{0: \App\Ai\Contracts\StatementExtractorInterface, 1: array{provider: string, model: string, base_url: ?string}}
     *
     * @throws AiException jika belum dikonfigurasi
     */
    public function forPusat(): array
    {
        $global = \App\Services\Ai\KuotaPenyataService::MASJID_GLOBAL;
        $keyRef = \App\Support\Setting::get('sp_ai_key_ref', null, $global);
        $model = \App\Support\Setting::get('sp_ai_model', null, $global);
        $baseUrl = \App\Support\Setting::get('sp_ai_base_url', null, $global) ?: null;

        if (!$keyRef || !$model) {
            throw new AiException('Ciri Semak Penyata belum dikonfigurasi oleh pentadbir sistem.');
        }

        $apiKey = $this->vault->get($keyRef);
        if ($apiKey === null || $apiKey === '') {
            throw new AiException('Kunci API pusat tidak ditemui dalam vault.');
        }

        return [
            new OpenAiDialect($apiKey, $model, $baseUrl),
            ['provider' => 'OPENAI', 'model' => $model, 'base_url' => $baseUrl],
        ];
    }

    /** Prompt penyata penuh = arahan + panduan COA masjid (mapping + hasil 400/450 + belanja 600/650). */
    public function buildPromptPenyata(int $masjidId): string
    {
        $panduan = [];

        $mappings = DB::table('coa_local_mapping as m')
            ->join('coa as c', 'c.id', '=', 'm.coa_id')
            ->where('m.masjid_id', $masjidId)
            ->orderBy('m.local_label')
            ->limit(30)
            ->get(['m.local_label', 'c.kod']);
        foreach ($mappings as $m) {
            $panduan[] = "- {$m->kod} = {$m->local_label}";
        }

        foreach ([['400-%', '450-%'], ['600-%', '650-%']] as $julat) {
            $coas = DB::table('coa')
                ->where('masjid_id', $masjidId)
                ->where('is_header', 0)->where('is_active', 1)
                ->where(fn ($q) => $q->where('kod', 'like', $julat[0])->orWhere('kod', 'like', $julat[1]))
                ->orderBy('kod')
                ->limit(30)
                ->get(['kod', 'nama']);
            foreach ($coas as $c) {
                $panduan[] = "- {$c->kod} = {$c->nama}";
            }
        }

        if (empty($panduan)) {
            return self::PROMPT_PENYATA;
        }

        return self::PROMPT_PENYATA
            ."\n\nSenarai kod COA masjid ini (guna untuk cadangan_coa):\n"
            .implode("\n", array_unique($panduan));
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
