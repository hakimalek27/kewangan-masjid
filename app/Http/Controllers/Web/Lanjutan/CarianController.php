<?php

namespace App\Http\Controllers\Web\Lanjutan;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Models\Kutipan;
use App\Models\Pembayaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fasa 9 — UX: Carian global topbar (/carian?q=) + suis bahasa (/bahasa/{lang}).
 * Carian merentas kutipan (no_resit/nama_pemberi/deskripsi), pembayaran
 * (baucer_no/pemohon/deskripsi) dan aset tetap (nama/kod) — keputusan
 * berkumpulan dengan pautan terus.
 */
class CarianController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));

        $kutipan = collect();
        $pembayaran = collect();
        $aset = collect();

        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

            $kutipan = Kutipan::aktif()
                ->where(fn ($w) => $w->where('no_resit', 'like', $like)
                    ->orWhere('nama_pemberi', 'like', $like)
                    ->orWhere('deskripsi', 'like', $like))
                ->orderByDesc('tarikh')->limit(25)->get();

            $pembayaran = Pembayaran::aktif()
                ->where(fn ($w) => $w->where('baucer_no', 'like', $like)
                    ->orWhere('pemohon', 'like', $like)
                    ->orWhere('deskripsi', 'like', $like))
                ->orderByDesc('tar_lulus')->limit(25)->get();

            $aset = FixedAsset::query()
                ->where(fn ($w) => $w->where('nama', 'like', $like)
                    ->orWhere('kod_aset', 'like', $like))
                ->orderByDesc('id')->limit(25)->get();
        }

        return view('lanjutan.carian', compact('q', 'kutipan', 'pembayaran', 'aset'));
    }

    /** Suis bahasa BM|EN — simpan dalam sesi, middleware SetLocale memakainya. */
    public function bahasa(Request $request, string $lang): RedirectResponse
    {
        abort_unless(in_array($lang, ['ms', 'en'], true), 404);
        $request->session()->put('lang', $lang);

        return back();
    }
}
