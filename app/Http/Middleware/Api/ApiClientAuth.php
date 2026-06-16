<?php

namespace App\Http\Middleware\Api;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengesahan klien API awam (spec §2):
 *   Authorization: Bearer <access_token>  +  X-Client-Key: <client_key>
 * Token dikeluarkan oleh POST /v1/auth/token dan disimpan dalam cache
 * "apitoken:{token}" => client_id (TTL 3600s). Klien terikat SATU masjid —
 * masjid_id klien diikat ke container (multi-tenant, spec §8).
 */
class ApiClientAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // API = JSON sahaja — paksa Accept supaya semua render() exception memilih JSON
        $request->headers->set('Accept', 'application/json');

        $token = $request->bearerToken();
        $clientKey = (string) $request->header('X-Client-Key', '');

        if (!$token || $clientKey === '') {
            return $this->unauthenticated('Header Authorization (Bearer) dan X-Client-Key diperlukan.');
        }

        $clientId = Cache::get('apitoken:'.$token);
        if (!$clientId) {
            return $this->unauthenticated('Token tiada atau telah luput.');
        }

        $client = ApiClient::withoutMasjidScope()->find($clientId);
        if (!$client || !$client->is_active || !hash_equals((string) $client->client_key, $clientKey)) {
            return $this->unauthenticated('Klien tidak sah atau tidak aktif.');
        }

        // IP allowlist (opsional, dipisah koma)
        if (filled($client->ip_allowlist)) {
            $dibenarkan = array_filter(array_map('trim', explode(',', $client->ip_allowlist)));
            if (!in_array($request->ip(), $dibenarkan, true)) {
                return $this->unauthenticated('IP tidak dibenarkan untuk klien ini.');
            }
        }

        // Multi-tenant: ikat masjid klien ke container (skop global BelongsToMasjid)
        app()->instance('current.masjid_id', (int) $client->masjid_id);
        $request->attributes->set('api_client', $client);

        $client->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }

    private function unauthenticated(string $message): Response
    {
        return response()->json([
            'error' => ['code' => 'UNAUTHENTICATED', 'message' => $message],
        ], 401);
    }
}
