<?php

namespace App\Services\Lanjutan;

use App\Models\Budget;
use App\Models\Coa;
use App\Services\Laporan\ReportService;
use App\Services\Security\AuditTrailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Belanjawan tahunan per akaun Belanja (jadual budget — UNIQUE masjid+tahun+coa).
 * Varians = peruntukan vs perbelanjaan sebenar (ReportService::profitLoss tahun).
 */
class BudgetService
{
    public function __construct(
        private ReportService $report,
        private AuditTrailService $audit,
    ) {
    }

    /** Senarai varians semua akaun Belanja boleh-pos untuk tahun. */
    public function varians(int $tahun, ?int $masjidId = null): Collection
    {
        $masjidId ??= app('current.masjid_id');

        $pl = $this->report->profitLoss("$tahun-01", "$tahun-12", $masjidId);
        $sebenar = collect($pl['belanja'])->keyBy('kod');

        $peruntukan = Budget::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('tahun', $tahun)
            ->pluck('amaun_peruntukan', 'coa_id');

        return Coa::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('is_header', 0)->where('is_active', 1)
            ->where('jenis', 'Belanja')
            ->orderBy('kod')
            ->get(['id', 'kod', 'nama'])
            ->map(function ($coa) use ($sebenar, $peruntukan) {
                $p = round((float) ($peruntukan[$coa->id] ?? 0), 2);
                $s = round((float) ($sebenar[$coa->kod]->amaun ?? 0), 2);

                return (object) [
                    'coa_id'     => $coa->id,
                    'kod'        => $coa->kod,
                    'nama'       => $coa->nama,
                    'peruntukan' => number_format($p, 2, '.', ''),
                    'sebenar'    => number_format($s, 2, '.', ''),
                    'baki'       => number_format($p - $s, 2, '.', ''),
                    'pct'        => $p > 0 ? (int) round($s / $p * 100) : null,
                    'melebihi'   => $p > 0 && $s > $p,
                ];
            });
    }

    /** Simpan peruntukan [coa_id => amaun]; amaun 0/kosong = padam baris. */
    public function simpan(int $tahun, array $peruntukan, ?int $masjidId = null): int
    {
        $masjidId ??= app('current.masjid_id');
        $disimpan = 0;

        foreach ($peruntukan as $coaId => $amaun) {
            $coaId = (int) $coaId;
            $amaun = round((float) $amaun, 2);

            if ($amaun <= 0) {
                Budget::withoutMasjidScope()
                    ->where('masjid_id', $masjidId)->where('tahun', $tahun)->where('coa_id', $coaId)
                    ->delete();
                continue;
            }

            Budget::withoutMasjidScope()->updateOrCreate(
                ['masjid_id' => $masjidId, 'tahun' => $tahun, 'coa_id' => $coaId],
                ['amaun_peruntukan' => number_format($amaun, 2, '.', '')],
            );
            $disimpan++;
        }

        $this->audit->log('UPDATE', 'budget', null,
            ['tahun' => $tahun, 'bilangan' => $disimpan], null, masjidId: $masjidId);

        return $disimpan;
    }

    /**
     * Semakan amaran belanjawan (endpoint /belanjawan/semak):
     * adakah (sebenar tahun semasa + jumlah baharu) melebihi peruntukan?
     */
    public function semak(int $coaId, float $jumlah, ?int $tahun = null, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');
        $tahun ??= (int) now()->year;

        $peruntukan = Budget::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('tahun', $tahun)
            ->where('coa_id', $coaId)
            ->value('amaun_peruntukan');

        if ($peruntukan === null) {
            return ['ada' => false, 'melebihi' => false, 'peruntukan' => null, 'sebenar' => null, 'baki' => null];
        }

        $sebenar = (float) DB::table('journal_entry as je')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->where('jv.status', 'POSTED')
            ->where('jv.masjid_id', $masjidId)
            ->whereBetween('jv.period_ym', ["$tahun-01", "$tahun-12"])
            ->where('je.coa_id', $coaId)
            ->selectRaw('COALESCE(SUM(je.debit - je.kredit),0) as b')
            ->value('b');

        $unjuran = round($sebenar + $jumlah, 2);
        $baki = round((float) $peruntukan - $unjuran, 2);

        return [
            'ada'        => true,
            'melebihi'   => $unjuran > (float) $peruntukan + 0.004,
            'peruntukan' => number_format((float) $peruntukan, 2, '.', ''),
            'sebenar'    => number_format($sebenar, 2, '.', ''),
            'baki'       => number_format($baki, 2, '.', ''),
        ];
    }
}
