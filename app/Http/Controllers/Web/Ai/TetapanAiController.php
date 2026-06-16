<?php

namespace App\Http\Controllers\Web\Ai;

use App\Ai\AiClientFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AiProviderRequest;
use App\Http\Requests\Ai\TelegramConfigRequest;
use App\Models\AiProviderConfig;
use App\Models\TgBotConfig;
use App\Services\Security\AuditTrailService;
use App\Services\Security\SecretVaultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

/**
 * Tetapan AI & Telegram (admin sahaja) — kunci API/token bot TIDAK pernah
 * disimpan plaintext dalam jadual; hanya rujukan vault (*_ref).
 */
class TetapanAiController extends Controller
{
    /** PNG 1×1 piksel untuk Uji Sambungan (panggilan vision sebenar paling murah). */
    private const PNG_UJIAN = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function __construct(
        private SecretVaultService $vault,
        private AuditTrailService $audit,
    ) {
    }

    public function index(): View
    {
        $providers = AiProviderConfig::orderByDesc('is_default')->orderBy('id')->get()
            ->each(fn ($p) => $p->setAttribute('api_key_masked', $this->vault->masked($p->api_key_ref) ?? '(tiada)'));

        $bot = TgBotConfig::first();
        $botTokenMasked = $bot ? ($this->vault->masked($bot->bot_token_ref) ?? '(tiada)') : null;

        return view('ai.tetapan-ai', [
            'providers' => $providers,
            'bot' => $bot,
            'botTokenMasked' => $botTokenMasked,
            'webhookUrl' => url('/webhook/telegram'),
            'webhookSecret' => (string) config('services.telegram.webhook_secret'),
        ]);
    }

    public function providerSimpan(AiProviderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $ref = $this->vault->put($data['api_key']);

        $provider = AiProviderConfig::create([
            'provider' => $data['provider'],
            'dialect' => $data['dialect'],
            'base_url' => $data['base_url'] ?? null,
            'model' => $data['model'],
            'api_key_ref' => $ref, // plaintext TIDAK disimpan dalam jadual
            'supports_vision' => (bool) ($data['supports_vision'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_default' => !AiProviderConfig::where('is_default', 1)->exists(),
        ]);

        $this->audit->log('CREATE', 'ai_provider_config', null, [
            'provider' => $data['provider'], 'model' => $data['model'],
        ], $provider->id);

        return redirect()->route('tetapan.ai')->with('success', 'Provider AI berjaya ditambah.');
    }

    public function providerKemaskini(AiProviderRequest $request, AiProviderConfig $provider): RedirectResponse
    {
        $data = $request->validated();

        $kemaskini = [
            'provider' => $data['provider'],
            'dialect' => $data['dialect'],
            'base_url' => $data['base_url'] ?? null,
            'model' => $data['model'],
            'supports_vision' => (bool) ($data['supports_vision'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if (!empty($data['api_key'])) {
            // putar kunci dalam vault — ref kekal, jadual tidak berubah
            $this->vault->put($data['api_key'], $provider->api_key_ref);
        }

        $provider->update($kemaskini);
        $this->audit->log('UPDATE', 'ai_provider_config', null, [
            'provider' => $data['provider'], 'model' => $data['model'],
        ], $provider->id);

        return redirect()->route('tetapan.ai')->with('success', 'Provider AI berjaya dikemaskini.');
    }

    public function providerDefault(AiProviderConfig $provider): RedirectResponse
    {
        AiProviderConfig::where('id', '<>', $provider->id)->update(['is_default' => 0]);
        $provider->update(['is_default' => 1, 'is_active' => 1]);

        $this->audit->log('UPDATE', 'ai_provider_config', null, ['is_default' => 1], $provider->id);

        return redirect()->route('tetapan.ai')->with('success', "{$provider->provider} dijadikan provider default.");
    }

    /** Uji Sambungan — panggilan vision sebenar dengan PNG 1×1. */
    public function providerUji(AiProviderConfig $provider, AiClientFactory $factory): RedirectResponse
    {
        try {
            $apiKey = $this->vault->get($provider->api_key_ref);
            if (!$apiKey) {
                throw new \RuntimeException('Kunci API tidak ditemui dalam vault.');
            }

            $extractor = match ($provider->dialect) {
                'openai' => new \App\Ai\Dialects\OpenAiDialect($apiKey, $provider->model, $provider->base_url),
                'anthropic' => new \App\Ai\Dialects\AnthropicDialect($apiKey, $provider->model, $provider->base_url),
                'gemini' => new \App\Ai\Dialects\GeminiDialect($apiKey, $provider->model, $provider->base_url),
            };

            $mula = microtime(true);
            $extractor->extract(
                base64_decode(self::PNG_UJIAN),
                'image/png',
                'Ini ujian sambungan. Pulangkan JSON {"confidence":100} sahaja.'
            );
            $ms = (int) round((microtime(true) - $mula) * 1000);

            return redirect()->route('tetapan.ai')
                ->with('success', "Uji sambungan {$provider->provider} ({$provider->model}) BERJAYA — {$ms}ms.");
        } catch (Throwable $e) {
            return redirect()->route('tetapan.ai')
                ->withErrors(['uji' => "Uji sambungan {$provider->provider} GAGAL: ".mb_substr($e->getMessage(), 0, 300)]);
        }
    }

    public function telegramSimpan(TelegramConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $bot = TgBotConfig::first();

        if (!$bot && empty($data['bot_token'])) {
            return back()->withInput()->withErrors(['bot_token' => 'Token bot wajib diisi semasa tetapan pertama.']);
        }

        $ref = $bot?->bot_token_ref;
        if (!empty($data['bot_token'])) {
            $ref = $this->vault->put($data['bot_token'], $ref); // ke vault, bukan jadual
        }

        $nilai = [
            'bot_token_ref' => $ref,
            'chat_id' => (int) $data['chat_id'],
            'default_jenis' => $data['default_jenis'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        $bot ? $bot->update($nilai) : $bot = TgBotConfig::create($nilai);

        $this->audit->log('UPDATE', 'tg_bot_config', null, [
            'chat_id' => $data['chat_id'], 'default_jenis' => $data['default_jenis'],
        ], $bot->id);

        return redirect()->route('tetapan.ai')->with('success', 'Tetapan Telegram berjaya disimpan.');
    }
}
