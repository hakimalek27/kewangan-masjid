<?php

namespace App\Services\Api;

use App\Jobs\SendWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;

/**
 * Webhook keluar (spec §6) — cari webhook_subscription aktif untuk event +
 * masjid, cipta webhook_delivery (PENDING) dan dispatch job SendWebhook
 * (queue 'webhook', retry automatik). Dipanggil dari controller API selepas
 * service transaksi berjaya — service TIDAK diubah.
 */
class WebhookDispatcher
{
    public function dispatch(string $event, array $data, ?int $masjidId = null): void
    {
        $masjidId ??= app()->bound('current.masjid_id') ? app('current.masjid_id') : null;
        if (!$masjidId) {
            return;
        }

        $subs = WebhookSubscription::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('event', $event)
            ->where('is_active', 1)
            ->get();

        foreach ($subs as $sub) {
            $delivery = WebhookDelivery::create([
                'subscription_id' => $sub->id,
                'event'           => $event,
                'payload'         => json_encode([
                    'event'     => $event,
                    'data'      => $data,
                    'timestamp' => now()->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE),
                'status'          => 'PENDING',
                'attempts'        => 0,
            ]);

            SendWebhook::dispatch($delivery->id);
        }
    }
}
