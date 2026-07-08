<?php

namespace App\Services\Ai;

use Symfony\Component\Process\Process;

/**
 * Pecah PDF (terutama IMBASAN/scan tanpa teks) kepada imej JPG setiap muka
 * menggunakan Poppler `pdftoppm`. Diperlukan kerana provider AI vision hanya
 * membaca muka PERTAMA bila PDF berbilang-muka dihantar terus — dengan memecah
 * setiap muka jadi imej berasingan, SEMUA transaksi + tarikh dapat dibaca.
 */
class PdfRenderService
{
    private function exe(string $nama): string
    {
        $bin = trim((string) config('spkm.poppler_bin'));

        return $bin
            ? rtrim($bin, '/\\').DIRECTORY_SEPARATOR.$nama.'.exe'
            : $nama; // kosong → guna PATH
    }

    /** Poppler tersedia? (pdftoppm boleh dijalankan) */
    public function tersedia(): bool
    {
        try {
            $p = new Process([$this->exe('pdftoppm'), '-h']);
            $p->setTimeout(15);
            $p->run();

            // pdftoppm -h keluar dgn kod !=0 tetapi mencetak "pdftoppm version" ke stderr.
            return str_contains($p->getOutput().$p->getErrorOutput(), 'pdftoppm');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Ekstrak TEKS terbenam setiap muka PDF (via pdftotext -layout). Pulangkan
     * array teks per muka (dipisah aksara form-feed \f yang disisip pdftotext).
     * PDF IMBASAN (image-only) memulangkan teks kosong → pemanggil tahu ia perlu
     * OCR imej. PDF DIGITAL memulangkan teks penuh → boleh dibaca terus (pantas +
     * tiada ralat OCR). Kosong jika Poppler tiada / gagal.
     *
     * @return string[]  teks setiap muka (ikut susunan)
     */
    public function ekstrakTeks(string $absPdfPath): array
    {
        try {
            $p = new Process([$this->exe('pdftotext'), '-layout', '-enc', 'UTF-8', $absPdfPath, '-']);
            $p->setTimeout(120);
            $p->run();

            if (! $p->isSuccessful()) {
                return [];
            }
            $out = $p->getOutput();
            if (trim($out) === '') {
                return [];
            }

            // pdftotext menyisip \f antara muka (dan biasanya di hujung).
            $muka = explode("\f", $out);

            return array_map('rtrim', $muka);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Bilangan muka PDF (via pdfinfo); 0 jika gagal. */
    public function bilMuka(string $absPdfPath): int
    {
        try {
            $p = new Process([$this->exe('pdfinfo'), $absPdfPath]);
            $p->setTimeout(30);
            $p->run();
            if (preg_match('/^Pages:\s+(\d+)/m', $p->getOutput(), $m)) {
                return (int) $m[1];
            }
        } catch (\Throwable) {
        }

        return 0;
    }

    /**
     * Render setiap muka PDF ke JPG dalam $destDir; pulangkan senarai laluan imej
     * (tersusun ikut nombor muka). Kosong jika gagal.
     *
     * @return string[]
     */
    public function renderKeImej(string $absPdfPath, string $destDir, ?int $dpi = null): array
    {
        $dpi ??= (int) config('spkm.penyata_dpi', 150);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }
        $prefix = $destDir.DIRECTORY_SEPARATOR.'muka';

        $p = new Process([
            $this->exe('pdftoppm'), '-jpeg', '-r', (string) $dpi, $absPdfPath, $prefix,
        ]);
        $p->setTimeout(600); // render 70+ muka boleh ambil masa
        $p->run();

        if (!$p->isSuccessful()) {
            throw new \RuntimeException('Gagal render PDF ke imej (pdftoppm): '.mb_substr($p->getErrorOutput(), 0, 200));
        }

        $imej = glob($prefix.'-*.jpg') ?: [];
        natsort($imej); // muka-1, muka-2, ... muka-10 (bukan susunan leksikal)

        return array_values($imej);
    }
}
