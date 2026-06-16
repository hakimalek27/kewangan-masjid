<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tetapan\BankRequest;
use App\Models\BankAccount;
use App\Models\Coa;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Setting Bank (replika bankinfo_list.php) — maksimum 3 slot.
 * "Padam" = status TIDAK AKTIF + digunakan=0, BUKAN delete sebenar
 * (akaun bank mungkin dirujuk transaksi lama).
 */
class BankController extends Controller
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function index(): View
    {
        $senarai = BankAccount::query()
            ->orderBy('slot')
            ->get()
            ->each(fn ($b) => $b->setRelation('coa', Coa::find($b->coa_id)));

        return view('tetapan.bank-index', compact('senarai'));
    }

    public function simpan(BankRequest $request): RedirectResponse
    {
        $data = [...$request->validated(), 'digunakan' => $request->boolean('digunakan')];
        $bank = BankAccount::create($data);
        $this->audit->log('CREATE', 'bank_account', null, $data, $bank->id);

        return redirect()->route('bank.index')->with('success', 'Bank berjaya didaftarkan');
    }

    public function edit(BankAccount $bank): View
    {
        return view('tetapan.bank-edit', compact('bank'));
    }

    public function kemaskini(BankRequest $request, BankAccount $bank): RedirectResponse
    {
        $data = [...$request->validated(), 'digunakan' => $request->boolean('digunakan')];
        $sebelum = $bank->only(array_keys($data));
        $bank->update($data);
        $this->audit->log('UPDATE', 'bank_account', $sebelum, $data, $bank->id);

        return redirect()->route('bank.index')->with('success', 'Maklumat bank berjaya dikemaskini');
    }

    /** "Padam" = nyahaktif sahaja — rekod kekal untuk rujukan transaksi. */
    public function padam(BankAccount $bank): RedirectResponse
    {
        $sebelum = $bank->only(['status', 'digunakan']);
        $bank->update(['status' => 'TIDAK AKTIF', 'digunakan' => 0]);
        $this->audit->log('DELETE', 'bank_account', $sebelum, ['status' => 'TIDAK AKTIF', 'digunakan' => 0], $bank->id);

        return redirect()->route('bank.index')->with('success', 'Bank dinyahaktifkan (rekod kekal untuk rujukan transaksi)');
    }
}
