<?php

namespace App\Services\Ai;

use App\Models\TgBotConfig;
use App\Services\Security\SecretVaultService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien Telegram Bot API — token diambil dari vault melalui
 * tg_bot_config.bot_token_ref (TIDAK pernah plaintext dalam jadual).
 * Semua panggilan melalui Http facade (boleh Http::fake dalam ujian).
 */
class TelegramService
{
    public function __construct(private string $botToken)
    {
    }

    /** Bina klien untuk masjid — guna tg_bot_config aktif + vault. */
    public static function forMasjid(int $masjidId): self
    {
        $config = TgBotConfig::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('is_active', 1)
            ->first();

        if (!$config) {
            throw new RuntimeException('Tiada konfigurasi bot Telegram aktif untuk masjid ini.');
        }

        $token = app(SecretVaultService::class)->get($config->bot_token_ref);
        if ($token === null || $token === '') {
            throw new RuntimeException('Token bot Telegram tidak ditemui dalam vault (ref: '.$config->bot_token_ref.').');
        }

        return new self($token);
    }

    /** getFile → file_path di pelayan Telegram. */
    public function getFilePath(string $fileId): string
    {
        $response = Http::timeout(60)
            ->get('https://api.telegram.org/bot'.$this->botToken.'/getFile', ['file_id' => $fileId]);

        $path = $response->json('result.file_path');
        if ($response->failed() || !$path) {
            throw new RuntimeException('Telegram getFile gagal: HTTP '.$response->status());
        }

        return $path;
    }

    /** Muat turun kandungan fail (bait mentah). */
    public function downloadFile(string $filePath): string
    {
        $response = Http::timeout(120)
            ->get('https://api.telegram.org/file/bot'.$this->botToken.'/'.$filePath);

        if ($response->failed()) {
            throw new RuntimeException('Telegram muat turun fail gagal: HTTP '.$response->status());
        }

        return $response->body();
    }

    /** Hantar mesej teks ke chat. Pulangkan true jika berjaya. */
    public function sendMessage(int|string $chatId, string $teks): bool
    {
        $response = Http::timeout(60)
            ->post('https://api.telegram.org/bot'.$this->botToken.'/sendMessage', [
                'chat_id' => $chatId,
                'text' => $teks,
            ]);

        return $response->successful();
    }
}
