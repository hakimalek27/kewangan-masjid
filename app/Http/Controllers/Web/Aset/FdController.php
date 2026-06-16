<?php

namespace App\Http\Controllers\Web\Aset;

use App\Http\Controllers\Controller;
use App\Http\Requests\Aset\FdRequest;
use App\Models\BankAccount;
use App\Models\Coa;
use App\Models\FdInvestment;
use App\Services\Transaksi\FdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FdController extends Controller
{
    public function __construct(private FdService $fd)
    {
    }

    /** Borang Daftar Pelaburan FD Baru (replika pelaburan_fd_new.php) — jurnal Dr FD / Cr Bank. */
    public function baru(): View
    {
        return view('fd.borang', [
            'opening' => false,
            'tajuk'   => 'Daftar Pelaburan Baru',
        ]);
    }

    public function simpan(FdRequest $request): RedirectResponse
    {
        $this->fd->create($this->petaData($request));

        return redirect()
            ->route('fd.senarai')
            ->with('success', 'Pelaburan FD berjaya disimpan');
    }

    /** Borang Daftar Pelaburan Lama (replika opening_fd_add.php) — TIADA jurnal (nilai melalui Baki Awal). */
    public function daftarLama(): View
    {
        return view('fd.borang', [
            'opening' => true,
            'tajuk'   => 'Daftar Pelaburan Lama',
        ]);
    }

    public function simpanOpening(FdRequest $request): RedirectResponse
    {
        $this->fd->createOpening($this->petaData($request));

        return redirect()
            ->route('fd.senarailama')
            ->with('success', 'Pelaburan lama berjaya didaftarkan (tiada jurnal — nilai melalui Baki Awal)');
    }

    /** Senarai Pelaburan Terkini (replika pelaburan_fd_list.php). */
    public function senarai(): View
    {
        return $this->paparSenarai(opening: false, tajuk: 'Senarai Pelaburan Terkini');
    }

    /** Senarai Pelaburan Terdahulu / opening (replika opening_fd_list.php). */
    public function senaraiLama(): View
    {
        return $this->paparSenarai(opening: true, tajuk: 'Senarai Pelaburan Terdahulu');
    }

    /** Borang Edit FD — hanya medan BUKAN-kewangan boleh diubah. */
    public function edit(FdInvestment $fd): View
    {
        return view('fd.edit', ['fd' => $fd]);
    }

    /** Kemaskini medan bukan-kewangan sahaja (jumlah/COA dikunci — jurnal POSTED). */
    public function kemaskini(Request $request, FdInvestment $fd): RedirectResponse
    {
        $data = $request->validate([
            'institusi'     => ['required', 'string', 'max:150'],
            'no_sijil'      => ['nullable', 'string', 'max:60'],
            'kadar_pct'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tempoh_bulan'  => ['nullable', 'integer', 'min:1', 'max:600'],
            'maturity_date' => ['nullable', 'date'],
            'keterangan'    => ['nullable', 'string', 'max:300'],
        ], [], [
            'institusi'     => 'Institusi',
            'no_sijil'      => 'No. Sijil',
            'kadar_pct'     => 'Kadar (%)',
            'tempoh_bulan'  => 'Tempoh (bulan)',
            'maturity_date' => 'Tarikh Matang',
            'keterangan'    => 'Keterangan',
        ]);

        $this->fd->kemaskini($fd, $data);

        return redirect()
            ->route($fd->is_opening ? 'fd.senarailama' : 'fd.senarai')
            ->with('success', 'Maklumat pelaburan FD berjaya dikemaskini (jumlah & akaun dikekalkan)');
    }

    public function matang(Request $request, FdInvestment $fd): RedirectResponse
    {
        $this->fd->mature($fd, $request->input('tarikh', now()->format('Y-m-d')));

        return back()->with('success', 'FD telah ditanda MATANG dan wang dipindah semula ke bank');
    }

    public function renew(FdInvestment $fd): RedirectResponse
    {
        $this->fd->renew($fd);

        return back()->with('success', 'FD telah ditanda DIPERBAHARUI');
    }

    public function padam(FdInvestment $fd): RedirectResponse
    {
        $this->fd->void($fd, 'Dipadam melalui senarai pelaburan');

        return back()->with('success', 'Pelaburan FD dan Jurnal berkaitan berjaya dipadam');
    }

    /** Borang hantar bank_account_id; service perlukan coa_bank_id (COA bank dipilih). */
    private function petaData(FdRequest $request): array
    {
        $data = $request->validated();
        $bank = BankAccount::findOrFail($data['bank_account_id']);
        $data['coa_bank_id'] = (int) $bank->coa_id;
        unset($data['bank_account_id']);

        return $data;
    }

    private function paparSenarai(bool $opening, string $tajuk): View
    {
        $senarai = FdInvestment::where('is_opening', $opening ? 1 : 0)
            ->where('status', '!=', 'DIPADAM')
            ->orderBy('tarikh')
            ->orderBy('id')
            ->get();

        $coaIds = $senarai->pluck('coa_fd_id')
            ->merge($senarai->pluck('coa_bank_id'))
            ->filter()->unique();

        $namaCoa = Coa::whereIn('id', $coaIds)->get(['id', 'kod', 'nama'])->keyBy('id');

        return view('fd.senarai', compact('senarai', 'namaCoa', 'opening', 'tajuk'));
    }
}
