<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Laporan\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /v1/reports/* (scope read:reports; by-program → read:programs) —
 * pembalut nipis ReportService (spec §4). Struktur data laporan dipulangkan
 * sebagaimana dikira (kunci BM = istilah domain SPPKMS, didokumen OpenAPI).
 */
class ReportController extends ApiController
{
    public function __construct(private ReportService $report)
    {
    }

    /** GET /v1/reports/trial-balance?cutoff=YYYY-MM-DD */
    public function trialBalance(Request $request): JsonResponse
    {
        $data = $request->validate(['cutoff' => ['required', 'date_format:Y-m-d']]);

        return response()->json([
            'cutoff' => $data['cutoff'],
            ...$this->report->trialBalance(substr($data['cutoff'], 0, 7)),
        ]);
    }

    /** GET /v1/reports/income-statement?from=YYYY-MM&to=YYYY-MM */
    public function incomeStatement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m'],
            'to'   => ['required', 'date_format:Y-m'],
        ]);

        return response()->json([
            'from' => $data['from'],
            'to'   => $data['to'],
            ...$this->report->profitLoss($data['from'], $data['to']),
        ]);
    }

    /** GET /v1/reports/balance-sheet?cutoff=YYYY-MM-DD */
    public function balanceSheet(Request $request): JsonResponse
    {
        $data = $request->validate(['cutoff' => ['required', 'date_format:Y-m-d']]);

        return response()->json([
            'cutoff' => $data['cutoff'],
            ...$this->report->balanceSheet(substr($data['cutoff'], 0, 7)),
        ]);
    }

    /** GET /v1/reports/by-program?from=YYYY-MM&to=YYYY-MM (scope read:programs) */
    public function byProgram(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m'],
            'to'   => ['nullable', 'date_format:Y-m'],
        ]);

        return response()->json([
            'from' => $data['from'] ?? null,
            'to'   => $data['to'] ?? null,
            'data' => $this->report->programReport($data['from'] ?? null, $data['to'] ?? null),
        ]);
    }
}
