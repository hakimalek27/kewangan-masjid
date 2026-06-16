<?php

namespace App\Http\Controllers\Web\Lanjutan;

use App\Http\Controllers\Controller;
use App\Services\Lanjutan\YearEndService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fasa 9 — Penutupan Tahun (/tetapan/tutup-tahun, admin sahaja).
 * Pratonton P&L tahun + amaran Akaun Sementara 300-99990 + butang Tutup.
 */
class TutupTahunController extends Controller
{
    public function __construct(private YearEndService $servis)
    {
    }

    public function index(Request $request): View
    {
        $tahun = (int) $request->input('tahun', now()->year - 1);

        return view('lanjutan.tutup-tahun', [
            'tahun'     => $tahun,
            'pratonton' => $this->servis->pratonton($tahun),
        ]);
    }

    public function tutup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tahun'           => ['required', 'integer', 'between:2000,2100'],
            'sahkan_suspense' => ['nullable', 'boolean'],
        ]);
        $tahun = (int) $data['tahun'];

        $pratonton = $this->servis->pratonton($tahun);

        if ($pratonton['sudah_tutup']) {
            return back()->withErrors(['tutup' => "Tahun $tahun telah pun ditutup (voucher YE-$tahun wujud)."]);
        }

        // Amaran kuat: suspense berbaki — boleh teruskan HANYA dengan pengesahan checkbox
        if ($pratonton['ada_suspense'] && empty($data['sahkan_suspense'])) {
            return back()->withErrors(['tutup' =>
                "Akaun Sementara 300-99990 masih berbaki RM {$pratonton['suspense']} — jelaskan suspense dahulu, "
                .'atau tandakan kotak pengesahan untuk meneruskan penutupan.']);
        }

        try {
            $voucher = $this->servis->tutup($tahun);
        } catch (\Throwable $e) {
            return back()->withErrors(['tutup' => 'Penutupan gagal: '.$e->getMessage()]);
        }

        return redirect()->route('tutuptahun.index', ['tahun' => $tahun])->with('success',
            "Tahun $tahun berjaya ditutup — voucher {$voucher->voucher_ref} (tempoh {$voucher->period_ym}). "
            ."Semua tempoh sehingga $tahun-12 kini DIKUNCI.");
    }
}
