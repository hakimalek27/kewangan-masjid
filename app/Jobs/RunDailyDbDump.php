<?php

namespace App\Jobs;

use App\Models\BackupConfig;
use App\Models\BackupQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use RuntimeException;

/**
 * Dump harian DB (mysqldump) → storage/app/private/backup-payload →
 * backup_queue jenis DB_DUMP bagi setiap masjid aktif bermod HARIAN →
 * dispatch RunBackupItem (sulit + muat naik Google Drive).
 *
 * Laluan binari dikonfigurasi env MYSQLDUMP_PATH
 * (cth Windows: C:\Users\hakim\xampp\mysql\bin\mysqldump.exe).
 */
class RunDailyDbDump implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 660; // > Process mysqldump (600s) supaya worker tidak bunuh job

    public function __construct()
    {
        $this->onQueue('backup');
    }

    public function handle(): void
    {
        $configs = BackupConfig::withoutMasjidScope()
            ->where('is_active', 1)
            ->get()
            ->filter(fn ($c) => str_contains((string) $c->backup_mode, 'HARIAN'));

        if ($configs->isEmpty()) {
            return;
        }

        $relPath = $this->jalankanDump();

        foreach ($configs as $config) {
            $item = BackupQueue::withoutMasjidScope()->create([
                'masjid_id'    => $config->masjid_id,
                'jenis'        => 'DB_DUMP',
                'payload_path' => $relPath,
                'status'       => 'PENDING',
            ]);

            RunBackupItem::dispatch($item->id);
        }
    }

    /** Jalankan mysqldump; pulangkan laluan relatif (disk 'local'). */
    private function jalankanDump(): string
    {
        $db = config('database.connections.'.config('database.default'));
        $bin = (string) config('spkm.mysqldump_path', 'mysqldump');

        $arahan = [
            $bin,
            '--host='.($db['host'] ?? '127.0.0.1'),
            '--port='.($db['port'] ?? 3306),
            '--user='.($db['username'] ?? 'root'),
            '--password='.($db['password'] ?? ''),
            '--single-transaction',
            '--routines',
            '--triggers',
            $db['database'],
        ];

        $process = new Process($arahan, timeout: 600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'mysqldump gagal (exit '.$process->getExitCode().'): '
                .mb_substr($process->getErrorOutput(), 0, 400)
            );
        }

        $sql = $process->getOutput();
        if (trim($sql) === '') {
            throw new RuntimeException('mysqldump memulangkan output kosong.');
        }

        // Mampatkan (gzip) sebelum simpan — teks SQL mampat ~85-90%, jadi simpanan
        // disk dan memori laluan sulit/muat-naik (RunBackupItem) mengecil ~10×.
        $gz = gzencode($sql, 6);
        unset($sql); // bebaskan memori segera

        $relPath = 'backup-payload/db-'.$db['database'].'-'.now()->format('Ymd-His').'.sql.gz';
        Storage::disk('local')->put($relPath, $gz);

        return $relPath;
    }
}
