<?php

namespace App\Http\Controllers\Web\Belanja;

use App\Http\Controllers\Controller;
use App\Http\Requests\Belanja\JurnalRequest;
use App\Services\Transaksi\BelanjaJurnalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JurnalController extends Controller
{
    public function __construct(private BelanjaJurnalService $jurnal)
    {
    }

    /** Borang Perbelanjaan Bukan Tunai — susut nilai/akruan/pembetulan (replika belanja_jurnal.php). */
    public function borang(): View
    {
        return view('belanja.jurnal');
    }

    public function simpan(JurnalRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $voucher = $this->jurnal->create(
            tarikh: $data['tarikh'],
            drCoaId: (int) $data['dr_coa_id'],
            crCoaId: (int) $data['cr_coa_id'],
            jumlah: $data['jumlah'],
            deskripsi: $data['deskripsi'],
        );

        return redirect()
            ->route('belanja.jurnal')
            ->with('success', "Jurnal {$voucher->voucher_ref} berjaya diproses");
    }
}
