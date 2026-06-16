<?php

namespace App\Http\Controllers\Web\Kutipan;

use App\Enums\SequenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kutipan\KutipanRequest;
use App\Models\Coa;
use App\Models\Kutipan;
use App\Models\PenyataSetting;
use App\Services\Accounting\NumberSequenceService;
use App\Services\Transaksi\KutipanService;
use App\Support\Terbilang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KutipanController extends Controller
{
    public function __construct(
        private KutipanService $kutipan,
        private NumberSequenceService $seq,
    ) {
    }

    /** Borang Kutipan Baru (replika kutipan_form.php). */
    public function baru(): View
    {
        return view('kutipan.baru', [
            'noResitSeterusnya' => $this->seq->peek(SequenceType::RESIT),
            'programSenarai'    => app(\App\Services\Laporan\ReportService::class)->programNames(),
        ]);
    }

    public function simpan(KutipanRequest $request): RedirectResponse
    {
        $kutipan = $this->kutipan->create($request->validated());

        return redirect()
            ->route('kutipan.view', $kutipan)
            ->with('success', 'Kutipan berjaya disimpan');
    }

    /** Paparan resit penuh + jurnal Dr/Cr (replika kutipan_view.php). */
    public function view(Kutipan $kutipan): View
    {
        $kutipan->load(['coa', 'bank', 'denominasi', 'fd', 'voucher.entries.coa' => fn ($q) => $q->withoutGlobalScope('masjid')]);

        return view('kutipan.view', [
            'kutipan'    => $kutipan,
            'modCetak'   => PenyataSetting::first()?->mode ?? 'SIGNATURE',
        ]);
    }

    /**
     * Cetakan resit format A4 (window.print). ?salinan=1|2 (2 = dua resit
     * serupa atas-bawah / 1 A4, koyak tengah); ?mod=SIGNATURE|DISCLAIMER
     * (lalai = Tetapan Penyata, fallback SIGNATURE).
     */
    public function cetak(Kutipan $kutipan, Request $request): View
    {
        $kutipan->load(['coa', 'bank']);

        $salinan = (int) $request->input('salinan') === 2 ? 2 : 1;
        $mod = in_array($request->input('mod'), ['SIGNATURE', 'DISCLAIMER'], true)
            ? $request->input('mod')
            : (PenyataSetting::first()?->mode ?? 'SIGNATURE');

        return view('cetak.resit', [
            'kutipan'   => $kutipan,
            'salinan'   => $salinan,
            'mod'       => $mod,
            'terbilang' => Terbilang::ringgit((float) $kutipan->jumlah),
        ]);
    }

    /** "Padam" = VOID melalui service (jejak audit kekal). */
    public function padam(Kutipan $kutipan): RedirectResponse
    {
        $this->kutipan->void($kutipan, 'Dipadam melalui paparan kutipan');

        return redirect()
            ->route('kutipan.senarai')
            ->with('success', 'Kutipan dan Jurnal berkaitan berjaya dipadam');
    }

    /** Senarai Kutipan bulanan (replika listingKutipan_v2.php). */
    public function senarai(Request $request): View
    {
        $periodYm = $this->periodYm($request);
        $coaId = (int) $request->input('coa_id') ?: null;

        $senarai = Kutipan::aktif()
            ->where('period_ym', $periodYm)
            ->when($coaId, fn ($q) => $q->where('coa_id', $coaId))
            ->with(['coa', 'bank'])
            ->orderBy('tarikh')
            ->orderBy('id')
            ->get();

        return view('kutipan.senarai', [
            'senarai'  => $senarai,
            'jumlah'   => $senarai->sum(fn ($k) => (float) $k->jumlah),
            'periodYm' => $periodYm,
        ]);
    }

    /** Kutipan Jumaat bulanan — tabung COA 400-01020. */
    public function jumaat(Request $request): View
    {
        return $this->senaraiTabung($request, '400-01020', 'Kutipan Jumaat');
    }

    /** Kutipan Harian bulanan — tabung COA 400-01010. */
    public function harian(Request $request): View
    {
        return $this->senaraiTabung($request, '400-01010', 'Kutipan Harian');
    }

    private function senaraiTabung(Request $request, string $kodCoa, string $tajuk): View
    {
        $periodYm = $this->periodYm($request);
        $coaId = Coa::where('kod', $kodCoa)->value('id');

        $senarai = Kutipan::aktif()
            ->where('period_ym', $periodYm)
            ->where('coa_id', $coaId ?? 0)
            ->orderBy('tarikh')
            ->orderBy('id')
            ->get();

        return view('kutipan.tabung-senarai', [
            'tajuk'    => $tajuk,
            'senarai'  => $senarai,
            'jumlah'   => $senarai->sum(fn ($k) => (float) $k->jumlah),
            'periodYm' => $periodYm,
        ]);
    }

    private function periodYm(Request $request): string
    {
        return sprintf('%04d-%02d', (int) $request->input('year', now()->year), (int) $request->input('bln', now()->month));
    }
}
