<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Laporan\StatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET /v1/balances (scope read:balances) — baki bank/tunai/PWR semasa
 * (StatementService::bakiTunaiSehingga period semasa) + baki FD (250-04xxx),
 * setiap satu {kod, nama, baki} (spec §4).
 */
class BalanceController extends ApiController
{
    public function __construct(private StatementService $statement)
    {
    }

    public function index(): JsonResponse
    {
        $period = now()->format('Y-m');
        $tunai = $this->statement->bakiTunaiSehingga($period);

        // FD (simpanan tetap) — baki jurnal akaun 250-04xxx
        $fd = DB::table('journal_entry as je')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->join('coa as c', 'c.id', '=', 'je.coa_id')
            ->where('jv.status', 'POSTED')
            ->where('jv.masjid_id', app('current.masjid_id'))
            ->where('jv.period_ym', '<=', $period)
            ->where('c.kod', 'like', '250-04%')
            ->groupBy('c.kod', 'c.nama')->orderBy('c.kod')
            ->selectRaw('c.kod, c.nama, ROUND(SUM(je.debit - je.kredit),2) as baki')
            ->get()
            ->map(fn ($r) => (object) ['kod' => $r->kod, 'nama' => $r->nama, 'baki' => number_format((float) $r->baki, 2, '.', '')]);

        $data = collect($tunai)
            ->map(fn ($r) => ['kod' => $r->kod, 'nama' => $r->nama, 'baki' => (string) $r->baki])
            ->merge($fd->map(fn ($r) => ['kod' => $r->kod, 'nama' => $r->nama, 'baki' => $r->baki]))
            ->values();

        return response()->json(['as_of' => $period, 'data' => $data]);
    }
}
