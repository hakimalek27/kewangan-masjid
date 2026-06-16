<?php

namespace App\Observers;

use App\Models\SecurityEvent;
use App\Services\Integration\AlertService;
use Throwable;

/**
 * Amaran Telegram automatik untuk security_event HIGH/CRITICAL —
 * best-effort selepas commit; kegagalan amaran tidak mengganggu pemanggil.
 */
class SecurityEventObserver
{
    public bool $afterCommit = true;

    public function created(SecurityEvent $event): void
    {
        if (!in_array($event->severity, ['HIGH', 'CRITICAL'], true)) {
            return;
        }

        try {
            if (app(AlertService::class)->securityEvent($event)) {
                // saveQuietly — jangan cetus observer/event lain
                $event->alerted = true;
                $event->saveQuietly();
            }
        } catch (Throwable) {
            // senyap — amaran adalah usaha terbaik
        }
    }
}
