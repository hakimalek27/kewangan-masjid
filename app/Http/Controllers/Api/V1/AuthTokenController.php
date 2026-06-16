<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * POST /v1/auth/token (spec §2) — tukar {client_key, secret} kepada
 * access_token rawak 64 aksara (cache "apitoken:{token}" => client_id,
 * TTL 3600s). Gagal → 401 UNAUTHENTICATED.
 */
class AuthTokenController extends ApiController
{
    private const TTL = 3600;

    public function token(Request $request): JsonResponse
    {
        $request->headers->set('Accept', 'application/json');

        $data = $request->validate([
            'client_key' => ['required', 'string'],
            'secret'     => ['required', 'string'],
        ]);

        $client = ApiClient::withoutMasjidScope()
            ->where('client_key', $data['client_key'])
            ->first();

        if (!$client || !$client->is_active || !Hash::check($data['secret'], $client->secret_hash)) {
            return $this->error('UNAUTHENTICATED', 'client_key atau secret tidak sah.', 401);
        }

        $token = Str::random(64);
        Cache::put('apitoken:'.$token, (int) $client->id, self::TTL);

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => self::TTL,
        ]);
    }
}
