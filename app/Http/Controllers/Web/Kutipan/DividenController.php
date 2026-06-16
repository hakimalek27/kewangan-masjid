<?php

namespace App\Http\Controllers\Web\Kutipan;

use App\Enums\SequenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kutipan\DividenRequest;
use App\Models\FdInvestment;
use App\Services\Accounting\NumberSequenceService;
use App\Services\Transaksi\KutipanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DividenController extends Controller
{
    public function __construct(
        private KutipanService $kutipan,
        private NumberSequenceService $seq,
    ) {
    }

    /** Borang Terimaan Dividen/Hibah FD (replika pelaburan_fd_dividen_new.php). */
    public function borang(Request $request): View
    {
        return view('kutipan.dividen', [
            'senaraiFd'         => FdInvestment::where('status', 'AKTIF')->orderBy('institusi')->get(),
            'fdDipilih'         => (int) $request->query('fd_id') ?: null,
            'noResitSeterusnya' => $this->seq->peek(SequenceType::RESIT),
        ]);
    }

    public function simpan(DividenRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $fd = FdInvestment::findOrFail($data['fd_id']);

        $kutipan = $this->kutipan->createDividen($fd, $data);

        return redirect()
            ->route('kutipan.view', $kutipan)
            ->with('success', 'Kutipan berjaya disimpan');
    }
}
