<?php

namespace App\Http\Controllers\Web\Kutipan;

use App\Enums\SequenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kutipan\TabungRequest;
use App\Services\Accounting\NumberSequenceService;
use App\Services\Transaksi\KutipanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TabungController extends Controller
{
    public function __construct(
        private KutipanService $kutipan,
        private NumberSequenceService $seq,
    ) {
    }

    /** Borang Kutipan Tabung — jadual denominasi 11 baris tetap (replika kutipan_tabung_form.php). */
    public function borang(): View
    {
        return view('kutipan.tabung', [
            'denominasi'        => TabungRequest::DENOMINASI,
            'noResitSeterusnya' => $this->seq->peek(SequenceType::RESIT),
        ]);
    }

    public function simpan(TabungRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Baris denominasi mengikut susunan tetap RM100 → 1 sen
        $denominasi = [];
        foreach (TabungRequest::DENOMINASI as $i => $denom) {
            $denominasi[] = [
                'denominasi' => $denom,
                'bilangan'   => (int) ($data['bilangan'][$i] ?? 0),
            ];
        }
        unset($data['bilangan']);

        // Tabung dikira tunai; tarikh transaksi = tarikh kiraan tabung
        $data['kaedah'] = 'TUNAI';
        $data['tarikh'] = $data['tar_kira'];

        $kutipan = $this->kutipan->createTabung($data, $denominasi);

        return redirect()
            ->route('kutipan.view', $kutipan)
            ->with('success', 'Kutipan berjaya disimpan');
    }
}
