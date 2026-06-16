<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Semakan scope (spec §2) — kolum SET `api_client.scopes` (rentetan dipisah
 * koma). Scope tidak mencukupi → 403 FORBIDDEN_SCOPE (spec §7).
 */
class ApiScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $client = $request->attributes->get('api_client');

        if (!$client || !self::ada($client->scopes, $scope)) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN_SCOPE', 'message' => "Scope '{$scope}' diperlukan untuk endpoint ini."],
            ], 403);
        }

        return $next($request);
    }

    /** Semak satu scope dalam rentetan SET 'a,b,c'. */
    public static function ada(?string $scopes, string $scope): bool
    {
        return in_array($scope, array_map('trim', explode(',', (string) $scopes)), true);
    }
}
