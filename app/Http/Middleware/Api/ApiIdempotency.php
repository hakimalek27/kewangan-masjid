<?php

namespace App\Http\Middleware\Api;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency (spec §3) — kritikal untuk audit kewangan, elak rekod berganda:
 *   - Semua POST WAJIB hantar header `Idempotency-Key` → 400 jika tiada.
 *   - Key sama diulang → respons ASAL dipulangkan (dari cache "idem:{client}:{key}",
 *     TTL 24 jam) — rekod baharu TIDAK dicipta.
 *   - Jika cache hilang tetapi api_request_log menunjukkan POST berjaya dengan
 *     key sama → 409 DUPLICATE (selamat: tidak mencipta pendua secara senyap).
 */
class ApiIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('POST')) {
            return $next($request);
        }

        $key = (string) $request->header('Idempotency-Key', '');
        if ($key === '') {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Header Idempotency-Key wajib untuk semua POST.',
                    'field'   => 'Idempotency-Key',
                ],
            ], 400);
        }

        $client = $request->attributes->get('api_client');
        $cacheKey = "idem:{$client->id}:{$key}";

        // Key sama diulang → pulangkan respons asal (tiada rekod kedua)
        if ($asal = Cache::get($cacheKey)) {
            return response($asal['body'], $asal['status'])
                ->header('Content-Type', 'application/json')
                ->header('Idempotency-Replayed', 'true');
        }

        // Cache hilang tetapi log menunjukkan POST berjaya dengan key sama → 409
        $pernahBerjaya = ApiRequestLog::query()
            ->where('client_id', $client->id)
            ->where('idempotency_key', $key)
            ->where('method', 'POST')
            ->whereBetween('status_code', [200, 299])
            ->exists();
        if ($pernahBerjaya) {
            return response()->json([
                'error' => ['code' => 'DUPLICATE', 'message' => 'Idempotency-Key telah digunakan untuk permintaan berjaya sebelum ini.'],
            ], 409);
        }

        $response = $next($request);

        // Simpan respons BERJAYA sahaja (24 jam) untuk dimainkan semula
        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body'   => $response->getContent(),
            ], now()->addDay());
        }

        return $response;
    }
}
