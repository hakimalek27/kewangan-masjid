<?php

namespace App\Services\Laporan;

use Illuminate\Support\Facades\DB;

/**
 * Dashboard — angka TUNAI (penemuan empirik kritikal):
 *   "Penerimaan"   = jumlah kutipan tunai masuk tahun semasa
 *   "Perbelanjaan" = jumlah TUNAI KELUAR (bayaran + beli aset + rekupmen + PWR)
 *                    ≠ P&L akruan. JANGAN campur dua-dua konsep.
 */
class DashboardService
{
    public function __construct(private StatementService $statement)
    {
    }

    public function ringkasan(int $tahun, ?int $masjidId = null): array
    {
        $r = $this->statement->ringkasanTunai(
            sprintf('%04d-01', $tahun), sprintf('%04d-12', $tahun), $masjidId
        );

        return [
            'penerimaan'   => $r['terima'],
            'perbelanjaan' => $r['bayar_tunai'],
        ];
    }

    /** Trend bulanan tahun semasa untuk carta (12 bulan: terima vs bayar tunai). */
    public function trendBulanan(int $tahun, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');

        $terima = DB::table('kutipan')
            ->where('masjid_id', $masjidId)->where('status', 'ACTIVE')
            ->where('period_ym', 'like', $tahun.'-%')->where('period_ym', '<>', $tahun.'-00')
            ->groupBy('period_ym')->selectRaw('period_ym, ROUND(SUM(jumlah),2) j')
            ->pluck('j', 'period_ym');

        $bayar = DB::table('pembayaran')
            ->where('masjid_id', $masjidId)->where('status', 'ACTIVE')
            ->where('period_ym', 'like', $tahun.'-%')->where('period_ym', '<>', $tahun.'-00')
            ->groupBy('period_ym')->selectRaw('period_ym, ROUND(SUM(jumlah),2) j')
            ->pluck('j', 'period_ym');

        $label = []; $sTerima = []; $sBayar = [];
        for ($b = 1; $b <= 12; $b++) {
            $ym = sprintf('%04d-%02d', $tahun, $b);
            $label[]   = $ym;
            $sTerima[] = (float) ($terima[$ym] ?? 0);
            $sBayar[]  = (float) ($bayar[$ym] ?? 0);
        }

        return ['label' => $label, 'terima' => $sTerima, 'bayar' => $sBayar];
    }
}
