<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Laporan\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboard): View
    {
        $tahun = now()->year;

        return view('dashboard.index', [
            'tahun'     => $tahun,
            'ringkasan' => $dashboard->ringkasan($tahun),
            'trend'     => $dashboard->trendBulanan($tahun),
        ]);
    }

    /** Halaman sementara untuk menu yang fasanya belum dibina */
    public function placeholder(string $tajuk = 'Dalam Pembinaan'): View
    {
        return view('placeholder', ['tajuk' => $tajuk]);
    }
}
