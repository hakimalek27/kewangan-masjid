<?php

namespace App\Observers\Concerns;

use App\Services\Api\WebhookDispatcher;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Cetus webhook transaction.created / transaction.voided dari peringkat
 * OBSERVER supaya SEMUA asal-usul transaksi (borang web, API /v1, pengesahan
 * draf AI) menghantar peristiwa yang sama melalui satu seam. Best-effort:
 * kegagalan webhook TIDAK menjejaskan transaksi (afterCommit + try/catch).
 */
trait EmitsTransactionWebhook
{
    protected function emitWebhook(Model $model, string $type, string $event): void
    {
        try {
            app(WebhookDispatcher::class)->dispatch($event, [
                'id'          => $model->getKey(),
                'type'        => $type, // 'receipt' | 'payment'
                'amount'      => (string) $model->jumlah,
                'voucher_ref' => optional($model->voucher)->voucher_ref,
            ], (int) $model->masjid_id);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
