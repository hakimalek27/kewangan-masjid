<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Asas controller API awam /v1 — format ralat seragam (spec §7):
 *   { "error": { "code": "...", "message": "...", "field": "..." } }
 */
abstract class ApiController extends Controller
{
    protected function error(string $code, string $message, int $status, ?string $field = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }

        return response()->json(['error' => $error], $status);
    }

    protected function client(Request $request): ApiClient
    {
        return $request->attributes->get('api_client');
    }

    /** Format baris jurnal Dr/Cr untuk respons (spec §5). */
    protected function journalDariVoucher($voucher): array
    {
        return $voucher->entries()->with('coa')->get()
            ->map(fn ($e) => [
                'coa'    => $e->coa->kod,
                'debit'  => (float) $e->debit,
                'credit' => (float) $e->kredit,
            ])->values()->all();
    }
}
