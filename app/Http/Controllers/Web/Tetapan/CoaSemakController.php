<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Http\Controllers\Controller;
use App\Models\Coa;
use App\Models\CoaLocalMapping;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Carian Padanan Kod Akaun (replika coa_semak.php) — carian fuzzy istilah
 * tempatan terhadap nama COA dan label tempatan masjid (similar_text %).
 */
class CoaSemakController extends Controller
{
    /** Skor minimum untuk dipaparkan. */
    private const AMBANG = 40.0;

    public function index(Request $request): View
    {
        $istilah = trim((string) $request->input('search'));
        $keputusan = collect();

        if ($istilah !== '') {
            $labels = CoaLocalMapping::query()
                ->get(['coa_id', 'local_label'])
                ->groupBy('coa_id');

            $keputusan = Coa::postable()
                ->orderBy('kod')
                ->get(['id', 'kod', 'nama'])
                ->map(function ($coa) use ($istilah, $labels) {
                    $skor = $this->peratus($istilah, $coa->nama);
                    $catatan = null;

                    foreach ($labels->get($coa->id, collect()) as $label) {
                        $p = $this->peratus($istilah, $label->local_label);
                        if ($p > $skor) {
                            $skor = $p;
                            $catatan = 'Padan label tempatan: '.$label->local_label;
                        }
                    }

                    return (object) ['kod' => $coa->kod, 'nama' => $coa->nama, 'skor' => $skor, 'catatan' => $catatan];
                })
                ->filter(fn ($r) => $r->skor >= self::AMBANG)
                ->sortByDesc('skor')
                ->values();
        }

        return view('tetapan.semak', compact('istilah', 'keputusan'));
    }

    /** Peratus persamaan similar_text (tidak sensitif huruf). */
    private function peratus(string $a, string $b): float
    {
        similar_text(mb_strtoupper($a), mb_strtoupper($b), $pct);

        return round($pct, 1);
    }
}
