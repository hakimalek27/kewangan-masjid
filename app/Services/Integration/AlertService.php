<?php

namespace App\Services\Integration;

use App\Models\BackupLog;
use App\Models\SecurityEvent;
use App\Models\TgBotConfig;
use App\Services\Ai\TelegramService;
use Throwable;

/**
 * Amaran pentadbir melalui Telegram (chat_id dalam tg_bot_config) untuk:
 *   - SecurityEvent HIGH/CRITICAL
 *   - BackupLog FAILED
 *   - Notis operasi (cth FD hampir matang, semakan integriti gagal)
 *
 * SEMUA kaedah best-effort (try/catch senyap) — kegagalan menghantar amaran
 * TIDAK boleh mengganggu aliran utama (transaksi, backup, log).
 */
class AlertService
{
    /** Hantar teks ke chat admin masjid. Pulangkan true jika berjaya. */
    public function hantar(int $masjidId, string $teks): bool
    {
        try {
            $config = TgBotConfig::withoutMasjidScope()
                ->where('masjid_id', $masjidId)
                ->where('is_active', 1)
                ->first();

            if (!$config) {
                return false;
            }

            return TelegramService::forMasjid($masjidId)
                ->sendMessage($config->chat_id, $teks);
        } catch (Throwable) {
            return false; // best-effort — jangan ganggu aliran pemanggil
        }
    }

    /** Hantar ke SEMUA masjid dengan bot aktif (amaran peringkat sistem). */
    public function hantarSemua(string $teks): void
    {
        try {
            TgBotConfig::withoutMasjidScope()
                ->where('is_active', 1)
                ->pluck('masjid_id')
                ->unique()
                ->each(fn ($mid) => $this->hantar((int) $mid, $teks));
        } catch (Throwable) {
            // senyap
        }
    }

    /** Amaran security_event HIGH/CRITICAL. Pulangkan true jika dihantar. */
    public function securityEvent(SecurityEvent $event): bool
    {
        if (!in_array($event->severity, ['HIGH', 'CRITICAL'], true)) {
            return false;
        }

        $teks = "⚠️ AMARAN KESELAMATAN [{$event->severity}]\n"
            ."Jenis: {$event->jenis}\n"
            .'Butiran: '.($event->detail ?? '-')."\n"
            .'IP: '.($event->ip_address ?? '-')."\n"
            .'Masa: '.now()->format('Y-m-d H:i:s');

        return $event->masjid_id
            ? $this->hantar((int) $event->masjid_id, $teks)
            : (bool) tap(true, fn () => $this->hantarSemua($teks));
    }

    /** Amaran backup gagal. */
    public function backupGagal(BackupLog $log): void
    {
        $teks = "❌ BACKUP GAGAL\n"
            ."Jenis: {$log->jenis}\n"
            .'Fail: '.($log->file_name ?? '-')."\n"
            .'Ralat: '.($log->error_text ?? '-')."\n"
            .'Masa: '.now()->format('Y-m-d H:i:s');

        $this->hantar((int) $log->masjid_id, $teks);
    }
}
