<?php

namespace App\Http\Controllers\Web\Belanja;

use App\Enums\SequenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Belanja\RekupmenRequest;
use App\Services\Accounting\NumberSequenceService;
use App\Services\Transaksi\PembayaranService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RekupmenController extends Controller
{
    public function __construct(
        private PembayaranService $pembayaran,
        private NumberSequenceService $seq,
    ) {
    }

    /** Borang Rekupmen PWR — pemindahan Bank → PWR, tiada kesan P&L (replika pwr_rekupmen.php). */
    public function borang(): View
    {
        return view('belanja.rekupmen', [
            'peekPwr' => $this->seq->peek(SequenceType::PWR),
        ]);
    }

    public function simpan(RekupmenRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Fasa 9 — Maker-Checker: bendahari + jumlah melebihi had kelulusan →
        // simpan permohonan PENDING (TIADA rekupmen/jurnal sehingga diluluskan)
        $kelulusan = app(\App\Services\Lanjutan\ApprovalService::class);
        if ($kelulusan->perluKelulusan((float) $data['jumlah'], $request->user())) {
            $approval = $kelulusan->mohon('REKUPMEN', (float) $data['jumlah'], $data);

            return redirect()
                ->route('belanja.senarai')
                ->with('success', 'Jumlah melebihi had kelulusan (RM '.number_format($kelulusan->had(), 2).") — permohonan #{$approval->id} menunggu kelulusan admin/pengerusi.");
        }

        $this->pembayaran->createRekupmen($data);

        return redirect()
            ->route('belanja.senarai')
            ->with('success', 'Rekupmen PWR berjaya disimpan');
    }
}
