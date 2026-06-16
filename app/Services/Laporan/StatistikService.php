<?php

namespace App\Services\Laporan;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistik — agregat COA × bulan (asas tunai, dari jadual sumber ACTIVE,
 * selari listing SPPKMS).
 */
class StatistikService
{
    /** Jadual 12 bulan × kategori untuk kutipan ('kutipan') atau belanja ('pembayaran'). */
    public function tahunan(string $jenis, int $tahun, ?int $coaId = null, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');
        $jadual = $jenis === 'kutipan' ? 'kutipan' : 'pembayaran';

        $rows = DB::table($jadual.' as t')
            ->join('coa as c', 'c.id', '=', 't.coa_id')
            ->where('t.masjid_id', $masjidId)->where('t.status', 'ACTIVE')
            ->when($jadual === 'pembayaran', fn ($q) => $q->whereIn('t.jenis', ['BAYARAN', 'ASET']))
            ->where('t.period_ym', 'like', $tahun.'-%')->where('t.period_ym', '<>', $tahun.'-00')
            ->when($coaId, fn ($q) => $q->where('t.coa_id', $coaId))
            ->groupBy('c.kod', 'c.nama', 't.period_ym')
            ->selectRaw('c.kod, c.nama, t.period_ym, ROUND(SUM(t.jumlah),2) as jumlah')
            ->get();

        $kategori = $rows->groupBy('kod')->map(function ($g) use ($tahun) {
            $bulanan = [];
            for ($b = 1; $b <= 12; $b++) {
                $ym = sprintf('%04d-%02d', $tahun, $b);
                $bulanan[$b] = number_format((float) ($g->firstWhere('period_ym', $ym)->jumlah ?? 0), 2, '.', '');
            }

            return (object) [
                'kod'     => $g->first()->kod,
                'nama'    => $g->first()->nama,
                'bulanan' => $bulanan,
                'jumlah'  => number_format((float) $g->sum('jumlah'), 2, '.', ''),
            ];
        })->sortKeys()->values();

        return [
            'kategori' => $kategori,
            'jumlah'   => number_format((float) $rows->sum('jumlah'), 2, '.', ''),
        ];
    }

    /** Perincian satu bulan ikut COA. */
    public function bulanan(string $jenis, string $periodYm, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');
        $jadual = $jenis === 'kutipan' ? 'kutipan' : 'pembayaran';

        $rows = DB::table($jadual.' as t')
            ->join('coa as c', 'c.id', '=', 't.coa_id')
            ->where('t.masjid_id', $masjidId)->where('t.status', 'ACTIVE')
            ->when($jadual === 'pembayaran', fn ($q) => $q->whereIn('t.jenis', ['BAYARAN', 'ASET']))
            ->where('t.period_ym', $periodYm)
            ->groupBy('c.kod', 'c.nama')->orderBy('c.kod')
            ->selectRaw('c.kod, c.nama, COUNT(*) as bil, ROUND(SUM(t.jumlah),2) as jumlah')
            ->get();

        return [
            'baris'  => $rows,
            'jumlah' => number_format((float) $rows->sum('jumlah'), 2, '.', ''),
        ];
    }

    /** Statistik Kutipan Jumaat — pecahan mingguan (setiap rekod tabung Jumaat dalam bulan). */
    public function jumaatMingguan(string $periodYm, ?int $masjidId = null): Collection
    {
        $masjidId ??= app('current.masjid_id');
        $coaId = DB::table('coa')->where('masjid_id', $masjidId)->where('kod', '400-01020')->value('id');

        return DB::table('kutipan')
            ->where('masjid_id', $masjidId)->where('status', 'ACTIVE')
            ->where('coa_id', $coaId)->where('period_ym', $periodYm)
            ->orderBy('tarikh')
            ->get(['tarikh', 'deskripsi', 'jumlah', 'no_resit']);
    }
}
