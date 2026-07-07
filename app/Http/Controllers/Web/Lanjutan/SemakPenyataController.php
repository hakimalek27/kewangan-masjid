<?php

namespace App\Http\Controllers\Web\Lanjutan;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Coa;
use App\Models\PenyataSemakan;
use App\Services\Ai\KuotaPenyataService;
use App\Services\Ai\SemakPenyataService;
use App\Services\Lanjutan\ReconciliationService;
use App\Support\MasjidRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Semak Penyata (AI)" (aras tenant) — muat naik penyata bank → AI ekstrak →
 * dua jadual: KIRI belum direkod (cadangan COA, butang Rekod/Abai), KANAN sudah
 * padan/direkod. "Rekod" = catat kutipan/belanja sebenar (semakan manusia).
 */
class SemakPenyataController extends Controller
{
    public function __construct(
        private SemakPenyataService $servis,
        private KuotaPenyataService $kuota,
        private ReconciliationService $recon,
    ) {
    }

    /** Baris mesti milik batch masjid semasa (PenyataSemakan berskop → 404 silang-tenant). */
    private function pastikanBarisMilikMasjid(BankStatementLine $line): void
    {
        abort_unless($line->batch_id && PenyataSemakan::whereKey($line->batch_id)->exists(), 404);
    }

    public function index(Request $request): View
    {
        $masjidId = (int) app('current.masjid_id');
        $banks = BankAccount::query()->where('status', 'AKTIF')->orderBy('slot')->get();

        $batches = PenyataSemakan::query()->orderByDesc('id')->limit(30)->get();
        $batchId = (int) $request->input('batch') ?: $batches->first()?->id;
        $batch = $batchId ? $batches->firstWhere('id', $batchId) : null;

        $belum = collect();
        $sudah = collect();
        $laporan = null;

        if ($batch) {
            $belum = $batch->baris()
                ->where('status', 'UNMATCHED')
                ->orderBy('tarikh')->orderBy('id')->get();

            $sudah = $batch->baris()
                ->whereIn('status', ['MATCHED', 'IGNORED'])
                ->orderBy('tarikh')->orderBy('id')->get();

            // Ref voucher untuk baris MATCHED (paparan pautan).
            $voucherIds = $sudah->pluck('matched_voucher_id')->filter()->all();
            $refs = $voucherIds
                ? DB::table('journal_voucher')->whereIn('id', $voucherIds)->pluck('voucher_ref', 'id')
                : collect();
            $sudah->each(fn ($l) => $l->voucher_ref = $refs[$l->matched_voucher_id] ?? null);

            $laporan = $this->recon->laporan((int) $batch->bank_account_id);
        }

        // Pilihan COA untuk modal Rekod: wang masuk = hasil (400/450) ATAU
        // tabung khusus liabiliti (300-04xxx — sah sebagai Cr kutipan); keluar = belanja.
        $coaHasil = Coa::query()->where('is_header', 0)->where('is_active', 1)
            ->where(fn ($q) => $q->where('kod', 'like', '400-%')->orWhere('kod', 'like', '450-%')
                ->orWhere('kod', 'like', '300-04%'))
            ->orderBy('kod')->get(['id', 'kod', 'nama']);
        $coaBelanja = Coa::query()->where('is_header', 0)->where('is_active', 1)
            ->where(fn ($q) => $q->where('kod', 'like', '600-%')->orWhere('kod', 'like', '650-%'))
            ->orderBy('kod')->get(['id', 'kod', 'nama']);

        return view('lanjutan.semak-penyata', [
            'banks' => $banks,
            'batches' => $batches,
            'batch' => $batch,
            'belum' => $belum,
            'sudah' => $sudah,
            'laporan' => $laporan,
            'coaHasil' => $coaHasil,
            'coaBelanja' => $coaBelanja,
            'kuotaBoleh' => $this->kuota->boleh($masjidId),
            'kuotaGuna' => $this->kuota->usedThisMonth($masjidId),
            'kuotaHad' => $this->kuota->effectiveLimit($masjidId),
            'kuotaBaki' => $this->kuota->remaining($masjidId),
            'kuotaGlobal' => $this->kuota->globallyEnabled(),
        ]);
    }

    /** Muat naik penyata (PDF/imej) → dispatch job AI. */
    public function muatNaik(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', MasjidRule::exists('bank_account')],
            'fail' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [], ['bank_account_id' => 'Bank', 'fail' => 'Fail Penyata']);

        try {
            $batch = $this->servis->muatNaik((int) $data['bank_account_id'], $request->file('fail'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['fail' => $e->getMessage()]);
        }

        return redirect()->route('semakpenyata.index', ['batch' => $batch->id])
            ->with('success', 'Penyata dimuat naik. AI sedang memproses — halaman akan dikemas kini automatik.');
    }

    /** Poll status batch (JSON) semasa pemprosesan AI. */
    public function status(PenyataSemakan $batch): JsonResponse
    {
        return response()->json([
            'status' => $batch->status,
            'bil_baris' => (int) $batch->bil_baris,
            'bil_auto_padan' => (int) $batch->bil_auto_padan,
            'error_text' => $batch->error_text,
        ]);
    }

    /** Papar fail penyata asal (stream berpagar). */
    public function fail(PenyataSemakan $batch): StreamedResponse
    {
        abort_unless($batch->file_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($batch->file_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response(
            $batch->file_path,
            $batch->original_name ?: basename($batch->file_path),
            ['Content-Type' => $batch->mime, 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    /** Rekod satu baris → catat kutipan/belanja sebenar. */
    public function rekod(Request $request, BankStatementLine $line): RedirectResponse
    {
        $this->pastikanBarisMilikMasjid($line);

        $data = $request->validate([
            'coa_id' => ['required', 'integer', MasjidRule::exists('coa')],
            'penerima' => ['nullable', 'string', 'max:200'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
        ], [], ['coa_id' => 'Kod Akaun']);

        try {
            $r = $this->servis->rekodBaris($line, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['rekod' => $e->getMessage()]);
        }

        $rujukan = $r['jenis'] === 'KUTIPAN' ? 'resit #'.$r['recno'] : 'baucer #'.$r['recno'];

        return redirect()->route('semakpenyata.index', ['batch' => $line->batch_id])
            ->with('success', "Baris penyata #{$line->id} direkod ({$rujukan}).");
    }

    /** Abai satu baris. */
    public function abai(BankStatementLine $line): RedirectResponse
    {
        $this->pastikanBarisMilikMasjid($line);

        try {
            $this->servis->abaiBaris($line);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['rekod' => $e->getMessage()]);
        }

        return redirect()->route('semakpenyata.index', ['batch' => $line->batch_id])
            ->with('success', "Baris penyata #{$line->id} diabaikan.");
    }
}
