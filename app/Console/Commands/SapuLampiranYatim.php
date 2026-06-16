<?php

namespace App\Console\Commands;

use App\Models\Approval;
use App\Models\Attachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sapu fail lampiran YATIM di storan peribadi (disk 'local', folder lampiran/)
 * yang: (a) TIADA row Attachment merujuknya, DAN (b) TIDAK wujud dalam payload
 * mana-mana permohonan kelulusan PENDING (_lampiran), DAN (c) lebih lama daripada
 * tempoh anggun (elak buang fail yang baru distash & belum sempat dirujuk).
 *
 * Fail sebegini terhasil bila bayaran maker-checker distash semasa permohonan
 * tetapi permohonan itu tidak pernah diputuskan (lulus/tolak). Ia BUKAN kebocoran
 * (storan peribadi, tiada row Attachment → endpoint belanja.lampiran 404) — cuma
 * kebersihan storan + elak simpan PII yatim selama-lamanya.
 */
class SapuLampiranYatim extends Command
{
    protected $signature = 'sppkms:sapu-lampiran {--hari=7 : Umur minimum fail (hari) sebelum layak dibuang} {--dry-run : Senarai sahaja, jangan padam}';

    protected $description = 'Padam fail lampiran yatim (tiada Attachment & bukan permohonan PENDING) di storan peribadi';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        if (! $disk->exists('lampiran')) {
            $this->info('Tiada folder lampiran — tiada apa untuk disapu.');

            return self::SUCCESS;
        }

        $ambang = now()->subDays(max(0, (int) $this->option('hari')))->getTimestamp();

        // Laluan fail yang MASIH dirujuk row Attachment (semua masjid).
        $dirujuk = Attachment::withoutMasjidScope()
            ->whereNotNull('file_path')->pluck('file_path')
            ->filter()->unique()->flip();

        // Laluan dalam payload permohonan PENDING (_lampiran) — belum diputus, jangan buang.
        foreach (Approval::withoutMasjidScope()->where('status', 'PENDING')->pluck('payload') as $payload) {
            $p = json_decode((string) $payload, true);
            foreach ((is_array($p) ? ($p['_lampiran'] ?? []) : []) as $l) {
                if (! empty($l['file_path'])) {
                    $dirujuk[$l['file_path']] = true;
                }
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $dibuang = 0;
        $bytes = 0;

        foreach ($disk->files('lampiran') as $fail) {
            if ($dirujuk->has($fail) || $disk->lastModified($fail) > $ambang) {
                continue; // masih dirujuk ATAU terlalu baharu (tempoh anggun)
            }

            $saiz = (int) $disk->size($fail);
            if ($dryRun) {
                $this->line("[dry-run] yatim: {$fail} ({$saiz} bytes)");
            } else {
                $disk->delete($fail);
                $this->line("dibuang: {$fail} ({$saiz} bytes)");
            }
            $dibuang++;
            $bytes += $saiz;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Selesai: {$dibuang} fail lampiran yatim".
            ($dibuang ? ' ('.number_format($bytes / 1024, 1).' KB)' : '').'.');

        return self::SUCCESS;
    }
}
