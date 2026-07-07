<?php

namespace App\Console\Commands;

use App\Models\BackupConfig;
use App\Services\Integration\GoogleDriveBackupService;
use Illuminate\Console\Command;
use Throwable;

/**
 * E6 — uji-pulih backup berkala: muat turun backup TERKINI setiap masjid (yang
 * backupnya aktif) dari Google Drive & sahkan checksum + boleh nyahsulit. Alert
 * dihantar (dalam service) jika gagal. No-op anggun jika tiada config/backup.
 */
class UjiPulihBackup extends Command
{
    protected $signature = 'spkm:uji-pulih-backup';
    protected $description = 'Uji-pulih (restore-test) backup terkini setiap masjid dari Google Drive';

    public function handle(GoogleDriveBackupService $service): int
    {
        $konfigs = BackupConfig::withoutMasjidScope()->where('is_active', 1)->get();
        if ($konfigs->isEmpty()) {
            $this->info('Tiada konfigurasi backup aktif — dilangkau.');

            return self::SUCCESS;
        }

        $adaGagal = false;
        foreach ($konfigs as $config) {
            try {
                $hasil = $service->ujiPulihTerkini((int) $config->masjid_id);
                $this->line(sprintf('Masjid #%d: %s', $config->masjid_id, $hasil['status']));
                if ($hasil['status'] === 'gagal') {
                    $adaGagal = true;
                }
            } catch (Throwable $e) {
                $adaGagal = true;
                $this->error(sprintf('Masjid #%d: ralat uji-pulih — %s', $config->masjid_id, $e->getMessage()));
            }
        }

        return $adaGagal ? self::FAILURE : self::SUCCESS;
    }
}
