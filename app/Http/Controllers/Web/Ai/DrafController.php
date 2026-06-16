<?php

namespace App\Http\Controllers\Web\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\DrafSahkanRequest;
use App\Models\AiExtraction;
use App\Models\DocInbox;
use App\Models\TxnDraft;
use App\Services\Ai\DraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kotak Draf AI — semakan manusia WAJIB sebelum draf menjadi transaksi.
 * Pengesahan/penolakan melalui DraftService (role admin/bendahari sahaja).
 */
class DrafController extends Controller
{
    public function __construct(private DraftService $servis)
    {
    }

    public function index(): View
    {
        $senarai = TxnDraft::query()
            ->where('status', 'PENDING_REVIEW')
            ->orderByDesc('id')
            ->paginate(25);

        $extractions = AiExtraction::whereIn('id', $senarai->pluck('extraction_id')->filter())
            ->get()->keyBy('id');

        return view('ai.draf-index', compact('senarai', 'extractions'));
    }

    public function lihat(TxnDraft $draf): View
    {
        $extraction = $draf->extraction_id ? AiExtraction::find($draf->extraction_id) : null;
        $inbox = $draf->inbox_id ? DocInbox::withoutMasjidScope()->find($draf->inbox_id) : null;

        return view('ai.draf-lihat', compact('draf', 'extraction', 'inbox'));
    }

    /** Stream imej resit dari storan private (auth sahaja). */
    public function imej(TxnDraft $draf): StreamedResponse
    {
        $inbox = $draf->inbox_id ? DocInbox::withoutMasjidScope()->find($draf->inbox_id) : null;
        abort_unless($inbox && $inbox->file_path && Storage::disk('local')->exists($inbox->file_path), 404);

        return Storage::disk('local')->response(
            $inbox->file_path,
            basename($inbox->file_path),
            ['Content-Type' => $inbox->file_type === 'PDF' ? 'application/pdf' : 'image/jpeg'],
        );
    }

    public function sahkan(DrafSahkanRequest $request, TxnDraft $draf): RedirectResponse
    {
        $data = $request->validated();

        try {
            // Bendahari boleh tukar jenis (KUTIPAN ↔ BAYARAN) sebelum pengesahan
            if (!empty($data['jenis']) && $data['jenis'] !== $draf->jenis) {
                $draf->update(['jenis' => $data['jenis']]);
                $draf->refresh();
            }

            $draf = $this->servis->confirm($draf, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['draf' => $e->getMessage()]);
        }

        return redirect()->route('draf.index')
            ->with('success', "Draf #{$draf->id} disahkan & direkodkan (rekod #{$draf->posted_recno}).");
    }

    public function tolak(Request $request, TxnDraft $draf): RedirectResponse
    {
        $sebab = (string) $request->input('sebab', '');

        try {
            $this->servis->reject($draf, mb_substr($sebab, 0, 300));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['draf' => $e->getMessage()]);
        }

        return redirect()->route('draf.index')->with('success', "Draf #{$draf->id} telah ditolak.");
    }
}
