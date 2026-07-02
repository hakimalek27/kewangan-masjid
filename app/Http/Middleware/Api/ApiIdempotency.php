<?php

namespace App\Http\Middleware\Api;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency (spec §3) — kritikal untuk audit kewangan, elak rekod berganda:
 *   - Semua POST WAJIB hantar header `Idempotency-Key` → 400 jika tiada.
 *   - Key sama + BADAN sama diulang → respons ASAL dipulangkan (cache 24 jam) — tiada rekod baharu.
 *   - Key sama + BADAN berbeza → 409 CONFLICT (elak main-semula respons salah).
 *   - Cache hilang tetapi log menunjukkan POST berjaya (key+path sama) → 409 DUPLICATE.
 *
 * B4/H1 — kunci ATOM (Cache::lock) per (client,path,key) mengehadkan hanya SATU
 * permintaan serentak melepasi pemeriksaan pada satu masa; permintaan retry serentak
 * yang kedua menunggu, kemudian memainkan semula respons asal (bukan mencipta pendua).
 * M1/E3 — kunci dinamakan mengikut PATH juga → key sama merentas endpoint berbeza
 * tidak berlanggar (cth /receipts vs /payments).
 */
class ApiIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
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

        $client   = $request->attributes->get('api_client');
        $path     = $request->path();
        $bodyHash = sha1((string) $request->getContent());
        $cacheKey = 'idem:'.$client->id.':'.sha1($path.'|'.$key);

        $lock = Cache::lock($cacheKey.':lock', 20);

        try {
            // Tunggu sehingga 10s untuk permintaan serentak yang sama selesai dahulu.
            $lock->block(10);
        } catch (LockTimeoutException) {
            return response()->json([
                'error' => ['code' => 'DUPLICATE', 'message' => 'Permintaan serupa sedang diproses. Cuba lagi sebentar.'],
            ], 409);
        }

        try {
            if ($asal = Cache::get($cacheKey)) {
                // Key sama tetapi BADAN berbeza → jangan main-semula respons salah.
                if (($asal['hash'] ?? null) !== $bodyHash) {
                    return response()->json([
                        'error' => ['code' => 'CONFLICT', 'message' => 'Idempotency-Key telah digunakan dengan kandungan permintaan berbeza.'],
                    ], 409);
                }

                return response($asal['body'], $asal['status'])
                    ->header('Content-Type', 'application/json')
                    ->header('Idempotency-Replayed', 'true');
            }

            // Cache hilang tetapi log menunjukkan POST berjaya (key + path sama) → 409
            $pernahBerjaya = ApiRequestLog::query()
                ->where('client_id', $client->id)
                ->where('idempotency_key', $key)
                ->where('path', $path)
                ->where('method', 'POST')
                ->whereBetween('status_code', [200, 299])
                ->exists();
            if ($pernahBerjaya) {
                return response()->json([
                    'error' => ['code' => 'DUPLICATE', 'message' => 'Idempotency-Key telah digunakan untuk permintaan berjaya sebelum ini.'],
                ], 409);
            }

            $response = $next($request);

            if ($response->isSuccessful()) {
                Cache::put($cacheKey, [
                    'status' => $response->getStatusCode(),
                    'body'   => $response->getContent(),
                    'hash'   => $bodyHash,
                ], now()->addDay());
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
