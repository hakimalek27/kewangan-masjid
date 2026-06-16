<?php

namespace App\Jobs;

use App\Models\BackupConfig;
use App\Models\BackupLog;
use App\Models\BackupQueue;
use App\Services\Integration\Contracts\GdriveClientInterface;
use App\Services\Security\SecretVaultService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Retensi backup (Fasa 7) — menguatkuasakan backup_config.retention_days:
 *   (a) Padam fail backup di Google Drive + baris backup_log lebih tua daripada
 *       retention_days bagi setiap masjid.
 *   (b) Jaring keselamatan: padam fail tempatan storage/app/private/backup-payload
 *       yang baris queue-nya DONE & lebih tua daripada 7 hari, KECUALI payload
 *       masih dirujuk baris PENDING/UPLOADING lain (dump DB dikongsi).
 */
class PruneBackups implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    private const HARI_PAYLOAD = 7;

    public function __construct()
    {
        $this->onQueue('backup');
    }

    public function handle(): void
    {
        $this->pruneDrive();
        $this->prunePayloadTempatan();
    }

    /** Padam fail Drive + backup_log melepasi retention_days per masjid. */
    private function pruneDrive(): void
    {
        $configs = BackupConfig::withoutMasjidScope()->where('is_active', 1)->get();

        foreach ($configs as $config) {
            $hari = max(1, (int) ($config->retention_days ?? 3650));
            $hadTarikh = now()->subDays($hari);

            $lama = BackupLog::withoutMasjidScope()
                ->where('masjid_id', $config->masjid_id)
                ->where('status', 'OK')
                ->whereNotNull('gdrive_file_id')
                ->where('created_at', '<', $hadTarikh)
                ->get();

            if ($lama->isEmpty()) {
                continue;
            }

            $klien = $this->klien($config);

            foreach ($lama as $log) {
                try {
                    if ($klien) {
                        $klien->deleteFile((string) $log->gdrive_file_id);
                    }
                    $log->delete();
                } catch (Throwable $e) {
                    report($e); // teruskan baris lain — retensi tidak boleh hentikan operasi
                }
            }
        }
    }

    /** Padam fail payload tempatan lama yang sudah selesai dimuat naik. */
    private function prunePayloadTempatan(): void
    {
        $hadTarikh = now()->subDays(self::HARI_PAYLOAD);

        $selesai = BackupQueue::withoutMasjidScope()
            ->where('status', 'DONE')
            ->whereNotNull('payload_path')
            ->where('created_at', '<', $hadTarikh)
            ->get();

        foreach ($selesai as $item) {
            // Jangan padam jika payload (cth dump DB) masih dirujuk kerja belum selesai
            $masihGuna = BackupQueue::withoutMasjidScope()
                ->where('payload_path', $item->payload_path)
                ->whereIn('status', ['PENDING', 'UPLOADING'])
                ->exists();

            if ($masihGuna) {
                continue;
            }

            try {
                if (Storage::disk('local')->exists($item->payload_path)) {
                    Storage::disk('local')->delete($item->payload_path);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function klien(BackupConfig $config): ?GdriveClientInterface
    {
        try {
            $saPath = $config->service_account_ref
                ? app(SecretVaultService::class)->get($config->service_account_ref)
                : null;

            // Tanpa parameter apabila tiada saPath supaya instance terikat
            // (cth klien palsu dalam ujian) digunakan; beri params hanya untuk produksi.
            return $saPath
                ? app(GdriveClientInterface::class, ['saJsonPath' => $saPath])
                : app(GdriveClientInterface::class);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
