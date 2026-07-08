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
        $jumMasuk = 0.0;
        $jumKeluar = 0.0;

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

            // Jumlah IKUT REKOD YANG AI SCAN (bukan jumlah tercetak dalam penyata) —
            // untuk bendahari banding tally penyata sebenar vs hasil AI.
            $agregat = DB::table('bank_statement_line')->where('batch_id', $batch->id)
                ->selectRaw('COALESCE(SUM(kredit),0) masuk, COALESCE(SUM(debit),0) keluar')->first();
            $jumMasuk = (float) ($agregat->masuk ?? 0);
            $jumKeluar = (float) ($agregat->keluar ?? 0);
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
            'jumMasuk' => $jumMasuk,
            'jumKeluar' => $jumKeluar,
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
        // Provider AI ditetapkan oleh SUPERADMIN untuk semua tenant (profil Default) —
        // tenant tidak memilih; muatNaik() akan guna provider Default aktif.
        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', MasjidRule::exists('bank_account')],
            'fail' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:102400'], // 100 MB
            // Persetujuan PDPA WAJIB — penyata bank = data peribadi; bukti disimpan.
            'pdpa_setuju' => ['accepted'],
        ], [
            'pdpa_setuju.accepted' => 'Anda mesti bersetuju dengan notis PDPA sebelum memuat naik penyata bank.',
        ], ['bank_account_id' => 'Bank', 'fail' => 'Fail Penyata']);

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
            'kaedah' => $batch->kaedah,
            'muka_jumlah' => (int) $batch->muka_jumlah,
            'muka_siap' => (int) $batch->muka_siap,
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

    /** Batalkan pemprosesan AI yang sedang berjalan (bendera → job berhenti awal). */
    public function batal(PenyataSemakan $batch): RedirectResponse
    {
        if (!in_array($batch->status, ['UPLOADED', 'AI_PROCESSING'], true)) {
            return back()->with('error', 'Penyata ini tidak sedang diproses.');
        }

        // UPLOADED (job belum mula) → terus DIBATAL; AI_PROCESSING → bendera (job
        // menyemak antara muka dan berhenti sendiri).
        $batch->update($batch->status === 'UPLOADED'
            ? ['status' => 'DIBATAL', 'batal_diminta' => true, 'error_text' => 'Dibatalkan oleh pengguna sebelum diproses.']
            : ['batal_diminta' => true]);

        return redirect()->route('semakpenyata.index', ['batch' => $batch->id])
            ->with('success', 'Permintaan batal dihantar — pemprosesan akan berhenti sebentar lagi.');
    }

    /** Padam kekal batch + fail (baris yang telah direkod ke lejar dikekalkan). */
    public function padam(PenyataSemakan $batch): RedirectResponse
    {
        try {
            $this->servis->padamBatch($batch);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['rekod' => $e->getMessage()]);
        }

        return redirect()->route('semakpenyata.index')
            ->with('success', 'Penyata & fail dipadam kekal. Untuk semak semula, muat naik & scan sekali lagi.');
    }

    /** Butiran satu voucher (untuk panel gelangsar "lihat rekod dalam sistem"). */
    public function voucher(int $voucher): JsonResponse
    {
        $masjidId = (int) app('current.masjid_id');
        $v = DB::table('journal_voucher')->where('id', $voucher)->where('masjid_id', $masjidId)->first();
        abort_unless($v, 404);

        $entries = DB::table('journal_entry as je')
            ->join('coa as c', 'c.id', '=', 'je.coa_id')
            ->where('je.voucher_id', $voucher)
            ->orderByDesc('je.debit')
            ->get(['c.kod', 'c.nama', 'je.debit', 'je.kredit', 'je.memo']);

        $pautan = match (strtoupper((string) $v->source_type)) {
            'KUTIPAN' => $v->source_id ? route('kutipan.view', $v->source_id) : null,
            'BAYARAN' => $v->source_id ? route('belanja.view', $v->source_id) : null,
            default => null,
        };

        return response()->json([
            'ref' => $v->voucher_ref,
            'tarikh' => $v->tarikh,
            'deskripsi' => $v->deskripsi,
            'status' => $v->status,
            'jumlah' => number_format((float) $entries->sum('debit'), 2),
            'entries' => $entries->map(fn ($e) => [
                'kod' => $e->kod, 'nama' => $e->nama, 'memo' => $e->memo,
                'debit' => (float) $e->debit > 0 ? number_format((float) $e->debit, 2) : '',
                'kredit' => (float) $e->kredit > 0 ? number_format((float) $e->kredit, 2) : '',
            ])->values(),
            'pautan' => $pautan,
        ]);
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

    /** Rekod beberapa baris sebagai SATU rekod lump-sum (longgok infaq QR dll). */
    public function rekodLumpSum(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'line_ids' => ['required', 'array', 'min:2'],
            'line_ids.*' => ['integer'],
            'coa_id' => ['required', 'integer', MasjidRule::exists('coa')],
            'tarikh' => ['required', 'date'],
            'penerima' => ['nullable', 'string', 'max:200'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'batch' => ['nullable', 'integer'],
        ], [], ['coa_id' => 'Kod Akaun', 'line_ids' => 'Baris', 'tarikh' => 'Tarikh']);

        // Sahkan setiap baris milik masjid semasa (batch berskop) — anti IDOR.
        foreach ($data['line_ids'] as $id) {
            $line = BankStatementLine::find((int) $id);
            abort_unless($line && $line->batch_id && PenyataSemakan::whereKey($line->batch_id)->exists(), 404);
        }

        try {
            $r = $this->servis->rekodLumpSum($data['line_ids'], $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['rekod' => $e->getMessage()]);
        }

        $ruj = $r['jenis'] === 'KUTIPAN' ? 'resit #'.$r['recno'] : 'baucer #'.$r['recno'];

        return redirect()->route('semakpenyata.index', ['batch' => $data['batch'] ?? null])
            ->with('success', "{$r['bil']} baris dilonggok jadi 1 rekod RM{$r['jumlah']} ({$ruj}).");
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
