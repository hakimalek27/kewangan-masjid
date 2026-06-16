<?php

namespace App\Services\Security;

use App\Models\SecurityEvent;

class SecurityEventService
{
    public function log(string $jenis, string $detail, string $severity = 'MEDIUM', ?int $userId = null): void
    {
        SecurityEvent::create([
            'masjid_id'  => app()->bound('current.masjid_id') ? app('current.masjid_id') : null,
            'user_id'    => $userId ?? (app()->bound('current.user_id') ? app('current.user_id') : null),
            'jenis'      => $jenis,
            'detail'     => substr($detail, 0, 500),
            'ip_address' => request()?->ip(),
            'severity'   => $severity,
        ]);
    }
}
