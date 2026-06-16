<?php

namespace App\Http\Controllers\Web\Lanjutan;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Services\Lanjutan\ReconciliationService;
use App\Support\MasjidRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Fasa 9 — Rekonsiliasi Bank: muat naik CSV penyata → padanan auto (amaun sama
 * ±3 hari) → padanan manual + laporan ringkas (penyata vs buku + beza).
 */
class RekonsiliasiController extends Controller
{
    public function __construct(private ReconciliationService $servis)
    {
    }

    public function index(Request $request): View
    {
        $banks = BankAccount::query()->where('status', 'AKTIF')->orderBy('slot')->get();
        $bankId = (int) $request->input('bank_account_id') ?: $banks->first()?->id;
        $bank = $bankId ? $banks->firstWhere('id', $bankId) : null;

        $unmatched = collect();
        $matched = collect();
        $laporan = null;
        $calon = collect();
        $lineCari = null;

        if ($bank) {
            $unmatched = BankStatementLine::query()
                ->where('bank_account_id', $bank->id)
                ->where('status', 'UNMATCHED')
                ->orderBy('tarikh')->orderBy('id')->limit(200)->get();

            $matched = BankStatementLine::query()
                ->where('bank_account_id', $bank->id)
                ->where('status', 'MATCHED')
                ->orderByDesc('tarikh')->orderByDesc('id')->limit(50)->get();

            $laporan = $this->servis->laporan($bank->id);

            // Carian voucher untuk padanan manual
            $lineCari = (int) $request->input('line') ?: null;
            $q = trim((string) $request->input('cari', ''));
            if ($lineCari) {
                $calon = DB::table('journal_voucher as jv')
                    ->join('journal_entry as je', 'je.voucher_id', '=', 'jv.id')
                    ->where('jv.status', 'POSTED')
                    ->where('jv.masjid_id', app('current.masjid_id'))
                    ->where('je.coa_id', $bank->coa_id)
                    ->when($q !== '', fn ($qq) => $qq->where(fn ($w) => $w
                        ->where('jv.voucher_ref', 'like', "%$q%")
                        ->orWhere('jv.deskripsi', 'like', "%$q%")
                        ->orWhere('je.debit', is_numeric($q) ? number_format((float) $q, 2, '.', '') : '-1')
                        ->orWhere('je.kredit', is_numeric($q) ? number_format((float) $q, 2, '.', '') : '-1')))
                    ->whereNotIn('jv.id', fn ($s) => $s->select('matched_voucher_id')
                        ->from('bank_statement_line')->whereNotNull('matched_voucher_id'))
                    ->orderByDesc('jv.tarikh')->orderByDesc('jv.id')
                    ->limit(20)
                    ->get(['jv.id', 'jv.voucher_ref', 'jv.tarikh', 'jv.deskripsi', 'je.debit', 'je.kredit']);
            }
        }

        return view('lanjutan.rekonsiliasi', compact('banks', 'bank', 'unmatched', 'matched', 'laporan', 'calon', 'lineCari'));
    }

    /** Muat naik CSV penyata bank → import + padanan auto. */
    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', MasjidRule::exists('bank_account')],
            'fail'            => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ], [], ['bank_account_id' => 'Bank', 'fail' => 'Fail CSV Penyata']);

        try {
            $r = $this->servis->importCsv(
                (int) $data['bank_account_id'],
                (string) file_get_contents($request->file('fail')->getRealPath()),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['fail' => $e->getMessage()]);
        }

        return redirect()->route('rekonsiliasi.index', ['bank_account_id' => $data['bank_account_id']])
            ->with('success', "Import selesai: {$r['diimport']} baris penyata, {$r['matched']} dipadan automatik, {$r['unmatched']} belum dipadan.");
    }

    /** Padanan manual baris penyata ↔ voucher. */
    public function padan(Request $request, BankStatementLine $line): RedirectResponse
    {
        $data = $request->validate(['voucher_id' => ['required', 'integer']], [], ['voucher_id' => 'Voucher']);

        try {
            $this->servis->padanManual($line, (int) $data['voucher_id']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['padan' => $e->getMessage()]);
        }

        return redirect()->route('rekonsiliasi.index', ['bank_account_id' => $line->bank_account_id])
            ->with('success', "Baris penyata #{$line->id} dipadankan dengan voucher #{$data['voucher_id']}.");
    }

    /** Abaikan baris penyata (bukan transaksi buku, cth caj bank lama). */
    public function abaikan(BankStatementLine $line): RedirectResponse
    {
        $this->servis->setStatus($line, 'IGNORED');

        return redirect()->route('rekonsiliasi.index', ['bank_account_id' => $line->bank_account_id])
            ->with('success', "Baris penyata #{$line->id} diabaikan.");
    }
}
