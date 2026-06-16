<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * POST payload webhook ke target_url klien (spec §6) dengan tandatangan
 * HMAC: `X-Signature: sha256=<hmac_sha256(body, secret)>`. Retry automatik
 * 5 kali (backoff 10s→15min); rekod status dalam webhook_delivery.
 */
class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int,int> */
    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('webhook');
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);
        if (!$delivery || $delivery->status === 'DELIVERED') {
            return;
        }

        $sub = WebhookSubscription::withoutMasjidScope()->find($delivery->subscription_id);
        if (!$sub || !$sub->is_active) {
            $delivery->update(['status' => 'FAILED']);

            return;
        }

        $body = (string) $delivery->payload;
        $signature = 'sha256='.hash_hmac('sha256', $body, (string) $sub->secret);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Signature'  => $signature,
                    'X-Event'      => $delivery->event,
                ])
                ->withBody($body, 'application/json')
                ->post($sub->target_url);

            $delivery->update([
                'attempts'      => $delivery->attempts + 1,
                'response_code' => $response->status(),
                'status'        => $response->successful() ? 'DELIVERED' : 'PENDING',
            ]);

            if (!$response->successful()) {
                throw new RuntimeException("Webhook {$sub->target_url} balas HTTP {$response->status()}");
            }
        } catch (RuntimeException $e) {
            throw $e; // retry mengikut backoff
        } catch (Throwable $e) {
            $delivery->update(['attempts' => $delivery->attempts + 1]);
            throw $e; // sambungan gagal — retry
        }
    }

    public function failed(Throwable $e): void
    {
        WebhookDelivery::whereKey($this->deliveryId)->update(['status' => 'FAILED']);
    }
}
