<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Coa;
use Illuminate\Http\JsonResponse;

/**
 * GET /v1/accounts (scope read:accounts) — senarai COA boleh-pos
 * {kod, nama, jenis} untuk masjid klien (spec §4).
 */
class AccountController extends ApiController
{
    public function index(): JsonResponse
    {
        $data = Coa::query()->postable()
            ->orderBy('kod')
            ->get(['kod', 'nama', 'jenis'])
            ->map(fn ($c) => ['kod' => $c->kod, 'nama' => $c->nama, 'jenis' => $c->jenis]);

        return response()->json(['data' => $data]);
    }
}
