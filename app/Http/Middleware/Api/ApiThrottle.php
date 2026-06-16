<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Had kadar per klien (spec §2): `api_client.rate_limit_per_min` panggilan
 * seminit (default 60). Lebih had → 429 RATE_LIMITED (spec §7).
 */
class ApiThrottle
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->attributes->get('api_client');
        $key = 'api-throttle:'.$client->client_key;
        $had = max(1, (int) ($client->rate_limit_per_min ?: 60));

        if (RateLimiter::tooManyAttempts($key, $had)) {
            return response()->json([
                'error' => ['code' => 'RATE_LIMITED', 'message' => "Had {$had} panggilan seminit dilampaui. Cuba lagi sebentar."],
            ], 429, ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
