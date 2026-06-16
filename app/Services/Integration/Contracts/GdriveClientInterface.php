<?php

namespace App\Services\Integration\Contracts;

/**
 * Lapisan klien Google Drive yang boleh di-mock — ujian mengikat instance
 * palsu pada interface ini dalam container (tiada panggilan rangkaian sebenar).
 */
interface GdriveClientInterface
{
    /**
     * Muat naik kandungan (bait mentah) ke Google Drive.
     *
     * @param  string $namaFail kandungan laluan logik cth "49/DB_DUMP/20260612-..."
     *                          ('/' dicipta sebagai hierarki subfolder)
     * @param  string $kandungan bait yang TELAH disulitkan
     * @param  string $folderId  ID folder Google Drive destinasi
     * @return string fileId Google Drive
     */
    public function upload(string $namaFail, string $kandungan, string $folderId): string;

    /** Padam fail Google Drive (retensi). Tiada-op jika fileId tidak wujud. */
    public function deleteFile(string $fileId): void;
}
