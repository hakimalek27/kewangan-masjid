<?php

namespace App\Http\Controllers\Web\Lanjutan;

use App\Http\Controllers\Controller;
use App\Models\TxnDraft;
use App\Services\Ai\DraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Fasa 9 — UX: Bulk sahkan draf AI (POST /draf/bulk-sahkan, admin & bendahari).
 * Setiap draf disahkan SATU-SATU melalui DraftService::confirm (laluan tunggal
 * draf → jurnal); kegagalan individu dikumpul dan dilaporkan ringkas.
 */
class DrafBulkController extends Controller
{
    public function __construct(private DraftService $servis)
    {
    }

    public function sahkan(Request $request): RedirectResponse
    {
        $ids = array_filter(array_map('intval', (array) $request->input('draf_ids', [])));

        if (!$ids) {
            return back()->withErrors(['bulk' => 'Tiada draf dipilih.']);
        }

        $senarai = TxnDraft::query()
            ->whereIn('id', $ids)
            ->where('status', 'PENDING_REVIEW')
            ->orderBy('id')
            ->get();

        $berjaya = 0;
        $gagal = [];

        foreach ($senarai as $draf) {
            try {
                $this->servis->confirm($draf);
                $berjaya++;
            } catch (Throwable $e) {
                $gagal[] = "Draf #{$draf->id}: ".$e->getMessage();
            }
        }

        $mesej = "Bulk pengesahan selesai: {$berjaya}/".count($senarai).' draf disahkan & direkodkan.';

        return $gagal
            ? redirect()->route('draf.index')->with('success', $mesej)->withErrors(['bulk' => $gagal])
            : redirect()->route('draf.index')->with('success', $mesej);
    }
}
