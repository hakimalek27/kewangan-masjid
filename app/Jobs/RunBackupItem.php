<?php

namespace App\Jobs;

use App\Models\BackupQueue;
use App\Services\Integration\GoogleDriveBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Proses satu baris backup_queue: PENDING → UPLOADING → DONE/FAILED.
 * Retry automatik 3 kali (backoff 30s→5min); kandungan disulitkan dan
 * dimuat naik oleh GoogleDriveBackupService.
 */
class RunBackupItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // sulit + muat naik Google Drive

    /** @var array<int,int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $backupQueueId)
    {
        $this->onQueue('backup');
    }

    public function handle(GoogleDriveBackupService $service): void
    {
        $item = BackupQueue::withoutMasjidScope()->find($this->backupQueueId);
        if (!$item || $item->status === 'DONE') {
            return;
        }

        $item->update(['status' => 'UPLOADING', 'attempts' => $item->attempts + 1]);

        try {
            $service->backupItem($item);
            $item->update(['status' => 'DONE']);
        } catch (Throwable $e) {
            // kembalikan ke PENDING untuk percubaan seterusnya; failed() akan
            // menanda FAILED selepas percubaan terakhir
            $item->update(['status' => 'PENDING']);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        BackupQueue::withoutMasjidScope()
            ->whereKey($this->backupQueueId)
            ->update(['status' => 'FAILED']);
    }
}
