<?php

namespace App\Services\Integration;

use App\Models\BackupConfig;
use App\Models\BackupLog;
use App\Models\BackupQueue;
use App\Services\Integration\Contracts\GdriveClientInterface;
use App\Services\Security\SecretVaultService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Backup luar tapak ke Google Drive (Fasa 7).
 *
 * Aliran: payload (fail dalam storage/app/private) → SULITKAN dengan APP_KEY
 * (Crypt::encryptString) → muat naik "{masjid}/{jenis}/{timestamp}-{nama}.enc"
 * → rekod backup_log (checksum SHA-256 atas kandungan TERSULIT).
 *
 * PENTING: APP_KEY mesti dibackup BERASINGAN — tanpa APP_KEY fail .enc tidak
 * boleh dipulihkan. ujiPulih() mengesahkan checksum + kebolehan nyahsulit.
 */
class GoogleDriveBackupService
{
    public function __construct(private ?GdriveClientInterface $client = null)
    {
    }

    /** Proses satu item backup_queue: baca payload → sulit → upload → log. */
    public function backupItem(BackupQueue $item): BackupLog
    {
        $config = BackupConfig::withoutMasjidScope()->find($item->masjid_id);

        try {
            if (!$config || !$config->is_active) {
                throw new RuntimeException('Konfigurasi backup tiada atau tidak aktif untuk masjid #'.$item->masjid_id);
            }

            $kandungan = $this->bacaPayload($item);
            $cipher = Crypt::encryptString($kandungan);

            $namaAsal = $item->payload_path ? basename($item->payload_path) : ($item->jenis.'-'.$item->id);
            $namaFail = sprintf(
                '%s/%s/%s-%s.enc',
                $item->masjid_id,
                $item->jenis,
                now()->format('Ymd-His'),
                $namaAsal
            );

            $fileId = $this->klien($config)->upload($namaFail, $cipher, (string) $config->gdrive_folder_id);

            $log = BackupLog::withoutMasjidScope()->create([
                'masjid_id'       => $item->masjid_id,
                'jenis'           => $item->jenis,
                'file_name'       => mb_substr($namaFail, 0, 200),
                'size_bytes'      => strlen($cipher),
                'checksum_sha256' => hash('sha256', $cipher),
                'gdrive_file_id'  => mb_substr($fileId, 0, 120),
                'status'          => 'OK',
            ]);

            $config->update(['last_backup_at' => now()]);

            return $log;
        } catch (Throwable $e) {
            $log = BackupLog::withoutMasjidScope()->create([
                'masjid_id'  => $item->masjid_id,
                'jenis'      => $item->jenis,
                'file_name'  => $item->payload_path ? mb_substr(basename($item->payload_path), 0, 200) : null,
                'status'     => 'FAILED',
                'error_text' => mb_substr($e->getMessage(), 0, 500),
            ]);

            // Amaran Telegram — usaha terbaik, tidak menghalang retry job
            app(AlertService::class)->backupGagal($log);

            throw $e;
        }
    }

    /**
     * Uji pulih: sahkan kandungan yang dimuat turun dari Drive masih utuh —
     * checksum padan DAN boleh dinyahsulit dengan APP_KEY semasa.
     */
    public function ujiPulih(BackupLog $log, string $kandunganMuatTurun): bool
    {
        if (hash('sha256', $kandunganMuatTurun) !== $log->checksum_sha256) {
            return false;
        }

        try {
            Crypt::decryptString($kandunganMuatTurun);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * E6 — uji-pulih backup TERKINI untuk sebuah masjid: muat turun dari Drive &
     * sahkan (checksum + boleh nyahsulit). Pulang ['status'=>..., 'log'=>...].
     * status: 'ok' | 'gagal' | 'tiada_backup' | 'tiada_config'.
     */
    public function ujiPulihTerkini(int $masjidId): array
    {
        $config = BackupConfig::withoutMasjidScope()->find($masjidId);
        if (! $config || ! $config->is_active) {
            return ['status' => 'tiada_config', 'log' => null];
        }

        $log = BackupLog::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('status', 'OK')
            ->whereNotNull('gdrive_file_id')
            ->latest('id')
            ->first();

        if (! $log) {
            return ['status' => 'tiada_backup', 'log' => null];
        }

        $kandungan = $this->klien($config)->download($log->gdrive_file_id);
        $ok = $this->ujiPulih($log, $kandungan);

        if (! $ok) {
            app(AlertService::class)->backupGagal($log);
        }

        return ['status' => $ok ? 'ok' : 'gagal', 'log' => $log];
    }

    private function bacaPayload(BackupQueue $item): string
    {
        if (!$item->payload_path) {
            throw new RuntimeException('Item backup_queue #'.$item->id.' tiada payload_path.');
        }

        // Laluan relatif kepada disk 'local' (storage/app/private)
        if (Storage::disk('local')->exists($item->payload_path)) {
            return (string) Storage::disk('local')->get($item->payload_path);
        }

        // Sokong juga laluan mutlak (cth dump sementara)
        if (is_file($item->payload_path)) {
            return (string) file_get_contents($item->payload_path);
        }

        throw new RuntimeException('Fail payload tidak ditemui: '.$item->payload_path);
    }

    private function klien(BackupConfig $config): GdriveClientInterface
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $saPath = null;
        if ($config->service_account_ref) {
            $saPath = app(SecretVaultService::class)->get($config->service_account_ref);
        }

        // Ujian boleh ikat instance palsu pada interface; produksi guna binding
        // AppServiceProvider yang membina GoogleDriveClient dengan laluan SA.
        return app(GdriveClientInterface::class, ['saJsonPath' => $saPath]);
    }
}
