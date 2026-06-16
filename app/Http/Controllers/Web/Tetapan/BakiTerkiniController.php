<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Services\Laporan\StatementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Baki Terkini (replika bank_monthly_balance.php) — baki bulanan satu akaun
 * bank, dikira terus daripada jurnal melalui StatementService.
 */
class BakiTerkiniController extends Controller
{
    public function __construct(private StatementService $statement)
    {
    }

    public function index(Request $request): View
    {
        $banks = BankAccount::orderBy('slot')->get();
        $tahun = (int) $request->input('tahun', now()->year);

        $bank = $request->filled('bank_id')
            ? $banks->firstWhere('id', (int) $request->input('bank_id'))
            : $banks->first();

        $bulanan = [];
        if ($bank) {
            foreach (range(1, 12) as $bln) {
                $ym = sprintf('%04d-%02d', $tahun, $bln);
                $bulanan[$bln] = $this->statement->pwrStatement((int) $bank->coa_id, $ym)['baki_akhir'];
            }
        }

        return view('tetapan.baki-terkini', compact('banks', 'bank', 'tahun', 'bulanan'));
    }
}
