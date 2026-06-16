<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Enums\SequenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tetapan\LogoRequest;
use App\Http\Requests\Tetapan\SiriRequest;
use App\Models\Masjid;
use App\Models\NumberSequence;
use App\Services\Accounting\NumberSequenceService;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Set Resit/Baucer (replika setResitBaucer.php) — digit & nombor mula
 * setiap siri (RESIT/PV/PWR/JNL); prefix lalai dikekalkan. + muat naik logo.
 */
class ResitBaucerController extends Controller
{
    private const SIRI = [
        'RESIT' => 'Resit Kutipan',
        'PV'    => 'Baucer Bayaran (PV)',
        'PWR'   => 'Baucer PWR',
        'JNL'   => 'Jurnal (JNL)',
    ];

    public function __construct(
        private NumberSequenceService $seq,
        private AuditTrailService $audit,
    ) {
    }

    public function index(): View
    {
        $sedia = NumberSequence::query()->get()->keyBy('jenis');

        $siri = collect(self::SIRI)->map(fn ($label, $jenis) => [
            'jenis'      => $jenis,
            'label'      => $label,
            'seterusnya' => $this->seq->peek(SequenceType::from($jenis)),
            'digit'      => (int) ($sedia[$jenis]->digit ?? 4),
            'mula'       => (int) ($sedia[$jenis]->next_no ?? 1),
        ])->values();

        return view('tetapan.resit', [
            'siri'   => $siri,
            'masjid' => Masjid::findOrFail((int) app('current.masjid_id')),
        ]);
    }

    public function setSiri(SiriRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $jenis = SequenceType::from($data['jenis']);

        $sedia = NumberSequence::where('jenis', $jenis->value)->first();
        $sebelum = $sedia?->only(['prefix', 'digit', 'next_no']);

        // Prefix lalai/sedia ada DIKEKALKAN — tiada input prefix dari borang
        $this->seq->setStart($jenis, (int) $data['digit'], (int) $data['mula'], $sedia?->prefix);

        $this->audit->log('UPDATE', 'number_sequence', $sebelum,
            ['jenis' => $jenis->value, 'digit' => $data['digit'], 'next_no' => $data['mula']], $sedia?->id);

        return redirect()->route('tetapan.resit')
            ->with('success', 'Siri '.self::SIRI[$jenis->value].' berjaya dikemaskini — nombor seterusnya: '.$this->seq->peek($jenis));
    }

    public function logo(LogoRequest $request): RedirectResponse
    {
        $masjid = Masjid::findOrFail((int) app('current.masjid_id'));
        $path = $request->file('logo')->store('logo', 'public');

        $sebelum = ['logo_path' => $masjid->logo_path];
        $masjid->update(['logo_path' => $path]);
        $this->audit->log('UPDATE', 'masjid', $sebelum, ['logo_path' => $path], $masjid->id);
        Masjid::lupakanSemasa(); // logo baharu terus tampak di semua view

        return redirect()->route('tetapan.resit')->with('success', 'Logo masjid berjaya dimuat naik');
    }
}
