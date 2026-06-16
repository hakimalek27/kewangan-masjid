<?php

namespace App\Jobs;

use App\Models\DocInbox;
use App\Services\Ai\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Langkah 1 pipeline: muat turun fail dari Telegram → simpan storan private →
 * semak pendua SHA-256 → seterusnya CallAiExtraction.
 * Idempotent: hanya proses inbox berstatus RECEIVED.
 */
class ProcessDocInbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 240; // muat turun fail Telegram (getFile + download)

    public function __construct(public int $docInboxId)
    {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $inbox = DocInbox::withoutMasjidScope()->find($this->docInboxId);
        if (!$inbox || $inbox->status !== 'RECEIVED') {
            return; // sudah diproses / tiada — idempotent
        }

        $telegram = TelegramService::forMasjid((int) $inbox->masjid_id);

        $bytes = $telegram->downloadFile($telegram->getFilePath($inbox->tg_file_id));
        $hash = hash('sha256', $bytes);

        // Anti-pendua kandungan: hash sama dalam masjid sama (abai yang REJECTED)
        $pendua = DocInbox::withoutMasjidScope()
            ->where('masjid_id', $inbox->masjid_id)
            ->where('file_hash', $hash)
            ->where('id', '<>', $inbox->id)
            ->where('status', '<>', 'REJECTED')
            ->exists();

        if ($pendua) {
            $inbox->update(['status' => 'DUPLICATE', 'file_hash' => $hash]);
            $telegram->sendMessage($inbox->tg_chat_id, '⚠️ Gambar ini sudah diproses sebelum ini.');

            return;
        }

        $ext = $inbox->file_type === 'PDF' ? 'pdf' : 'jpg';
        $path = 'inbox/'.$inbox->id.'.'.$ext;
        Storage::disk('local')->put($path, $bytes); // storage/app/private/inbox/

        $inbox->update([
            'status' => 'DOWNLOADED',
            'file_path' => $path,
            'file_hash' => $hash,
        ]);

        CallAiExtraction::dispatch($inbox->id);
    }
}
