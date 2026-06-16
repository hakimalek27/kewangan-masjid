<?php

namespace App\Jobs;

use App\Models\BackupConfig;
use App\Models\BackupQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Eksport jejak audit + security event 24 jam terakhir sebagai JSON →
 * backup_queue jenis LOG → muat naik luar tapak (tersulit). Salinan luar
 * tapak memastikan hash-chain audit boleh disahkan walau DB tempatan rosak.
 */
class UploadAuditLogOffsite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300; // sulit + muat naik Google Drive

    public function __construct()
    {
        $this->onQueue('backup');
    }

    public function handle(): void
    {
        $configs = BackupConfig::withoutMasjidScope()
            ->where('is_active', 1)
            ->get()
            ->filter(fn ($c) => str_contains((string) $c->backup_mode, 'LOG'));

        $sejak = now()->subDay()->format('Y-m-d H:i:s');

        foreach ($configs as $config) {
            $audit = DB::table('audit_trail')
                ->where('masjid_id', $config->masjid_id)
                ->where('created_at', '>=', $sejak)
                ->orderBy('id')
                ->get();

            $events = DB::table('security_event')
                ->where('masjid_id', $config->masjid_id)
                ->where('created_at', '>=', $sejak)
                ->orderBy('id')
                ->get();

            if ($audit->isEmpty() && $events->isEmpty()) {
                continue; // tiada aktiviti — tiada apa untuk dibackup
            }

            $payload = json_encode([
                'masjid_id'      => $config->masjid_id,
                'dieksport_pada' => now()->format('Y-m-d H:i:s'),
                'liputan_sejak'  => $sejak,
                'audit_trail'    => $audit,
                'security_event' => $events,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $relPath = 'backup-payload/log-'.$config->masjid_id.'-'.now()->format('Ymd-His').'.json';
            Storage::disk('local')->put($relPath, $payload);

            $item = BackupQueue::withoutMasjidScope()->create([
                'masjid_id'    => $config->masjid_id,
                'jenis'        => 'LOG',
                'payload_path' => $relPath,
                'status'       => 'PENDING',
            ]);

            RunBackupItem::dispatch($item->id);
        }
    }
}
