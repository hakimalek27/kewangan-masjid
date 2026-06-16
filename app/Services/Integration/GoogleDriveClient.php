<?php

namespace App\Services\Integration;

use App\Services\Integration\Contracts\GdriveClientInterface;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use RuntimeException;

/**
 * Implementasi sebenar Google Drive (google/apiclient) — autentikasi melalui
 * service account JSON. Laluan fail JSON datang daripada:
 *   1) vault (backup_config.service_account_ref → laluan fail), ATAU
 *   2) env GDRIVE_SA_JSON_PATH.
 * Kredensial TIDAK pernah disimpan plaintext dalam jadual aplikasi.
 */
class GoogleDriveClient implements GdriveClientInterface
{
    private ?Drive $drive = null;

    public function __construct(private ?string $saJsonPath = null)
    {
    }

    public function upload(string $namaFail, string $kandungan, string $folderId): string
    {
        $drive = $this->drive();

        // '/' dalam nama logik = hierarki subfolder (Google Drive tidak mentafsir
        // '/' dalam nama fail). Cipta/cari setiap segmen folder; fail diletak dalam
        // folder akhir dengan nama segmen terakhir sahaja.
        $segmen = array_values(array_filter(explode('/', $namaFail), fn ($s) => $s !== ''));
        $namaAkhir = array_pop($segmen) ?: $namaFail;
        $indukId = $folderId;
        foreach ($segmen as $folder) {
            $indukId = $this->ensureFolder($folder, $indukId);
        }

        $meta = new DriveFile([
            'name'    => $namaAkhir,
            'parents' => $indukId !== '' ? [$indukId] : [],
        ]);

        $file = $drive->files->create($meta, [
            'data'       => $kandungan,
            'mimeType'   => 'application/octet-stream',
            'uploadType' => 'multipart',
            'fields'     => 'id',
            'supportsAllDrives' => true,
        ]);

        $fileId = (string) $file->getId();
        if ($fileId === '') {
            throw new RuntimeException('Google Drive tidak memulangkan fileId.');
        }

        return $fileId;
    }

    public function deleteFile(string $fileId): void
    {
        if ($fileId === '') {
            return;
        }

        try {
            $this->drive()->files->delete($fileId, ['supportsAllDrives' => true]);
        } catch (\Google\Service\Exception $e) {
            // 404 = sudah tiada (idempoten); lain dilempar semula
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    /** Cari folder bernama di bawah induk; cipta jika tiada. Pulangkan ID folder. */
    private function ensureFolder(string $nama, string $indukId): string
    {
        $drive = $this->drive();
        $namaEscape = str_replace("'", "\\'", $nama);
        $q = "mimeType='application/vnd.google-apps.folder' and trashed=false and name='{$namaEscape}'"
            .($indukId !== '' ? " and '{$indukId}' in parents" : '');

        $senarai = $drive->files->listFiles([
            'q' => $q,
            'fields' => 'files(id)',
            'pageSize' => 1,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        if (count($senarai->getFiles()) > 0) {
            return (string) $senarai->getFiles()[0]->getId();
        }

        $folder = $drive->files->create(new DriveFile([
            'name'     => $nama,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents'  => $indukId !== '' ? [$indukId] : [],
        ]), ['fields' => 'id', 'supportsAllDrives' => true]);

        return (string) $folder->getId();
    }

    private function drive(): Drive
    {
        if ($this->drive !== null) {
            return $this->drive;
        }

        $path = $this->saJsonPath ?: (string) env('GDRIVE_SA_JSON_PATH', '');
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException(
                'Fail service account Google tidak ditemui — tetapkan melalui halaman Backup '
                .'(muat naik JSON) atau env GDRIVE_SA_JSON_PATH. Laluan: '.($path ?: '(kosong)')
            );
        }

        $client = new GoogleClient();
        $client->setAuthConfig($path);
        $client->addScope(Drive::DRIVE_FILE);

        return $this->drive = new Drive($client);
    }
}
