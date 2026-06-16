<?php

namespace App\Observers\Concerns;

use App\Jobs\RunBackupItem;
use App\Models\BackupConfig;
use App\Models\BackupQueue;
use App\Models\JournalEntry;
use App\Models\JournalVoucher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Logik kongsi observer backup per-transaksi (Fasa 7).
 *
 * REKA BENTUK: service transaksi (Kutipan/Pembayaran/Journal) TIDAK diubah —
 * observer Eloquent ($afterCommit = true) menangkap created/updated SELEPAS
 * COMMIT sahaja, jadi backup tidak sekali-kali menyentuh atau melengahkan
 * logik kewangan. Sebarang ralat backup ditelan senyap (best-effort) dan
 * dilog ke error_log.
 */
trait QueuesTransactionBackup
{
    /**
     * Masukkan snapshot transaksi ke backup_queue jenis TRANSACTION dan
     * dispatch RunBackupItem — hanya jika BackupConfig masjid aktif dan
     * mod mengandungi PER_TRANSAKSI.
     */
    protected function masukBarisanBackup(Model $model, string $entiti, ?int $voucherId = null): void
    {
        try {
            $masjidId = (int) $model->masjid_id;

            $config = BackupConfig::withoutMasjidScope()->find($masjidId);
            if (!$config || !$config->is_active || !str_contains((string) $config->backup_mode, 'PER_TRANSAKSI')) {
                return;
            }

            $payload = [
                'entiti'  => $entiti,
                'id'      => $model->getKey(),
                'rekod'   => $model->attributesToArray(),
                'voucher' => null,
                'entries' => [],
            ];

            $voucherId ??= $model->voucher_id ?? null;
            if ($voucherId) {
                $voucher = JournalVoucher::withoutMasjidScope()->find($voucherId);
                if ($voucher) {
                    $payload['voucher'] = $voucher->attributesToArray();
                    $payload['entries'] = JournalEntry::where('voucher_id', $voucher->id)
                        ->get()->map(fn ($e) => $e->attributesToArray())->all();
                }
            }

            $relPath = sprintf(
                'backup-payload/%s-%d-%s.json',
                $entiti,
                $model->getKey(),
                now()->format('Ymd-His-u')
            );
            Storage::disk('local')->put($relPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $item = BackupQueue::withoutMasjidScope()->create([
                'masjid_id'    => $masjidId,
                'jenis'        => 'TRANSACTION',
                'ref_id'       => $model->getKey(),
                'payload_path' => $relPath,
                'status'       => 'PENDING',
            ]);

            RunBackupItem::dispatch($item->id);
        } catch (Throwable $e) {
            // Backup TIDAK boleh mengganggu transaksi — log senyap sahaja
            try {
                \App\Models\ErrorLog::withoutMasjidScope()->create([
                    'masjid_id' => $model->masjid_id ?? null,
                    'level'     => 'WARNING',
                    'message'   => mb_substr('Gagal barisan backup '.$entiti.' #'.$model->getKey().': '.$e->getMessage(), 0, 500),
                ]);
            } catch (Throwable) {
                // senyap sepenuhnya
            }
        }
    }
}
