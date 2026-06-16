<?php

namespace App\Http\Middleware\Api;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Log setiap panggilan API (spec §2) ke `api_request_log` — terminable
 * (selepas respons dihantar): client_id, method, path, status, ip,
 * idempotency_key, latency_ms.
 */
class ApiRequestLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('api_mula', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $mula = (float) $request->attributes->get('api_mula', microtime(true));

            ApiRequestLog::create([
                'client_id'       => $request->attributes->get('api_client')?->id,
                'method'          => substr($request->method(), 0, 8),
                'path'            => substr('/'.$request->path(), 0, 200),
                'status_code'     => $response->getStatusCode(),
                'ip_address'      => $request->ip(),
                'idempotency_key' => substr((string) $request->header('Idempotency-Key', ''), 0, 80) ?: null,
                'latency_ms'      => (int) round((microtime(true) - $mula) * 1000),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal log api_request_log: '.$e->getMessage());
        }
    }
}
