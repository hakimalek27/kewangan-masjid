<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\CoaLocalMapping;
use App\Models\Masjid;
use App\Models\NumberSequence;
use App\Models\OpeningBalance;
use Illuminate\View\View;

/**
 * Wizard Setup (Fasa 4) — aliran berpandu untuk pemasangan segar:
 * bank → baki awal → pemetaan kod → no siri resit/baucer → logo.
 * Lapisan orkestrasi sahaja — setiap langkah menuju halaman tetapan sedia ada;
 * tiada logik kewangan baharu (mutasi kekal melalui Services).
 */
class SetupWizardController extends Controller
{
    public function index(): View
    {
        $masjidId = app('current.masjid_id');

        $langkah = [
            [
                'no'      => 1,
                'tajuk'   => 'Daftar Akaun Bank',
                'huraian' => 'Tetapkan akaun bank masjid (slot 1–3) dan petakan ke COA 250-050x0.',
                'route'   => 'bank.index',
                'siap'    => BankAccount::withoutMasjidScope()->where('masjid_id', $masjidId)->where('status', 'AKTIF')->exists(),
            ],
            [
                'no'      => 2,
                'tajuk'   => 'Set Baki Awal',
                'huraian' => 'Masukkan baki awal tahun (bank/aset/liabiliti) dan muktamadkan.',
                'route'   => 'bank.opening',
                'siap'    => OpeningBalance::withoutMasjidScope()->where('masjid_id', $masjidId)->exists(),
            ],
            [
                'no'      => 3,
                'tajuk'   => 'Set Kod Penerimaan/Perbelanjaan',
                'huraian' => 'Petakan label tempatan masjid kepada COA piawai.',
                'route'   => 'tetapan.mapping',
                'siap'    => CoaLocalMapping::withoutMasjidScope()->where('masjid_id', $masjidId)->exists(),
            ],
            [
                'no'      => 4,
                'tajuk'   => 'Set No. Resit/Baucer',
                'huraian' => 'Tetapkan turutan nombor resit, PV, PWR dan jurnal.',
                'route'   => 'tetapan.resit',
                'siap'    => NumberSequence::withoutMasjidScope()->where('masjid_id', $masjidId)->exists(),
            ],
            [
                'no'      => 5,
                'tajuk'   => 'Muat Naik Logo Masjid',
                'huraian' => 'Logo dipaparkan pada resit dan penyata kewangan.',
                'route'   => 'tetapan.resit',
                'siap'    => filled(Masjid::find($masjidId)?->logo_path),
            ],
        ];

        $bilSiap = collect($langkah)->where('siap', true)->count();

        return view('tetapan.wizard', [
            'langkah' => $langkah,
            'bilSiap' => $bilSiap,
            'jumlah'  => count($langkah),
            'peratus' => (int) round($bilSiap / count($langkah) * 100),
        ]);
    }
}
