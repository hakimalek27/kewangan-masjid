<?php

namespace App\Http\Controllers\Web\Belanja;

use App\Enums\SequenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Belanja\AsetBelanjaRequest;
use App\Http\Requests\Belanja\BayaranRequest;
use App\Models\Attachment;
use App\Models\Coa;
use App\Models\Pembayaran;
use App\Models\PenyataSetting;
use App\Services\Accounting\NumberSequenceService;
use App\Services\Transaksi\PembayaranService;
use App\Support\Terbilang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BelanjaController extends Controller
{
    public function __construct(
        private PembayaranService $pembayaran,
        private NumberSequenceService $seq,
    ) {
    }

    /** Menu Perbelanjaan — 4 kad pautan (replika perbelanjaan.php). */
    public function menu(): View
    {
        return view('belanja.menu');
    }

    /** Borang Bayaran Perbelanjaan (replika belanja_expense.php; ?mode=pwr = Bayaran PWR). */
    public function baru(Request $request): View
    {
        $modePwr = $request->query('mode') === 'pwr';

        return view('belanja.baru', [
            'modePwr'         => $modePwr,
            'tajuk'           => $modePwr ? 'BAYARAN PANJAR WANG RUNCIT (PWR)' : 'Bayaran Perbelanjaan',
            'peekPv'          => $this->seq->peek(SequenceType::PV),
            'peekPwr'         => $this->seq->peek(SequenceType::PWR),
            'programSenarai'  => app(\App\Services\Laporan\ReportService::class)->programNames(),
        ]);
    }

    public function simpan(BayaranRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Fasa 9 — Maker-Checker: bendahari + jumlah melebihi had kelulusan →
        // simpan permohonan PENDING (TIADA pembayaran/jurnal sehingga diluluskan)
        $kelulusan = app(\App\Services\Lanjutan\ApprovalService::class);
        if ($kelulusan->perluKelulusan((float) $data['jumlah'], $request->user())) {
            // Simpan dokumen sokongan DAHULU (fail tak boleh diserikan ke payload JSON) —
            // metadata masuk payload & dipautkan kpd pembayaran sebenar semasa diluluskan
            // (ApprovalService::lulus) supaya lampiran tidak hilang pada laluan maker-checker.
            $data['_lampiran'] = $this->stashLampiran($request);
            $approval = $kelulusan->mohon('BAYARAN', (float) $data['jumlah'], $data);

            $bilDok = count($data['_lampiran']);

            return redirect()
                ->route('belanja.senarai')
                ->with('success', 'Jumlah melebihi had kelulusan (RM '.number_format($kelulusan->had(), 2).") — permohonan #{$approval->id}".
                    ($bilDok ? " ({$bilDok} dokumen sokongan disimpan)" : '').' menunggu kelulusan admin/pengerusi.');
        }

        $pembayaran = $this->pembayaran->createBayaran($data);
        $this->simpanLampiran($request, $pembayaran);

        return redirect()
            ->route('belanja.senarai')
            ->with('success', 'Pembayaran berjaya disimpan');
    }

    /** Borang Pembelian Aset (replika belanja_asset.php). */
    public function aset(): View
    {
        return view('belanja.aset', [
            'coaAset' => $this->coaAsetTanpaSnt(),
            'peekPv'  => $this->seq->peek(SequenceType::PV),
        ]);
    }

    public function simpanAset(AsetBelanjaRequest $request): RedirectResponse
    {
        $pembayaran = $this->pembayaran->createAset($request->validated());
        $this->simpanLampiran($request, $pembayaran);

        return redirect()
            ->route('belanja.senarai')
            ->with('success', 'Pembayaran dan pendaftaran aset berjaya disimpan');
    }

    /** Buku Tunai Pembayaran (replika listingPembayaran_v2.php). */
    public function senarai(Request $request): View
    {
        $periodYm = sprintf('%04d-%02d', (int) $request->input('year', now()->year), (int) $request->input('bln', now()->month));
        $coaId = (int) $request->input('coa_id') ?: null;

        $senarai = Pembayaran::aktif()
            ->where('period_ym', $periodYm)
            ->when($coaId, fn ($q) => $q->where('coa_id', $coaId))
            ->with(['coa', 'bank', 'pwrCoa'])
            ->orderBy('tar_lulus')
            ->orderBy('id')
            ->get();

        return view('belanja.senarai', [
            'senarai'  => $senarai,
            'jumlah'   => $senarai->sum(fn ($p) => (float) $p->jumlah),
            'periodYm' => $periodYm,
        ]);
    }

    /** Paparan baucer penuh + jurnal Dr/Cr (replika belanja_view.php). */
    public function view(Pembayaran $pembayaran): View
    {
        $pembayaran->load(['coa', 'bank', 'pwrCoa', 'aset', 'voucher.entries.coa' => fn ($q) => $q->withoutGlobalScope('masjid')]);

        $lampiran = Attachment::where('owner_type', 'BAYARAN')
            ->where('owner_id', $pembayaran->id)
            ->get();

        return view('belanja.view', [
            'pembayaran' => $pembayaran,
            'lampiran'   => $lampiran,
            'modCetak'   => PenyataSetting::first()?->mode ?? 'SIGNATURE',
        ]);
    }

    /**
     * Cetakan baucer bayaran format A4 (window.print). ?mod=SIGNATURE|DISCLAIMER
     * (lalai = Tetapan Penyata, fallback SIGNATURE). Digunakan untuk semua
     * Pembayaran (bayaran/aset/PWR/rekupmen).
     */
    public function cetak(Pembayaran $pembayaran, Request $request): View
    {
        $pembayaran->load(['coa', 'bank', 'pwrCoa']);

        $mod = in_array($request->input('mod'), ['SIGNATURE', 'DISCLAIMER'], true)
            ? $request->input('mod')
            : (PenyataSetting::first()?->mode ?? 'SIGNATURE');

        return view('cetak.baucer', [
            'pembayaran' => $pembayaran,
            'mod'        => $mod,
            'terbilang'  => Terbilang::ringgit((float) $pembayaran->jumlah),
        ]);
    }

    /** "Padam" = VOID melalui service (jejak audit kekal). */
    public function padam(Pembayaran $pembayaran): RedirectResponse
    {
        $this->pembayaran->void($pembayaran, 'Dipadam melalui Buku Tunai Pembayaran');

        return redirect()
            ->route('belanja.senarai')
            ->with('success', 'Pembayaran dan Jurnal berkaitan berjaya dipadam');
    }

    /** Buku Tunai PWR (replika listingPWR_v1.php) — bayaran cara_bayar='PWR'. */
    public function pwrBuku(Request $request): View
    {
        $periodYm = sprintf('%04d-%02d', (int) $request->input('year', now()->year), (int) $request->input('bln', now()->month));
        $pwrCoaId = (int) $request->input('pwr_coa_id') ?: null;

        $senarai = Pembayaran::aktif()
            ->where('cara_bayar', 'PWR')
            ->where('period_ym', $periodYm)
            ->when($pwrCoaId, fn ($q) => $q->where('pwr_coa_id', $pwrCoaId))
            ->with(['coa', 'pwrCoa'])
            ->orderBy('tar_lulus')
            ->orderBy('id')
            ->get();

        return view('pwr.buku', [
            'senarai'  => $senarai,
            'jumlah'   => $senarai->sum(fn ($p) => (float) $p->jumlah),
            'periodYm' => $periodYm,
        ]);
    }

    /** Pembayaran PWR = borang belanja dalam mod PWR (replika belanja_expense.php?mode=pwr). */
    public function pwrBayar(): RedirectResponse
    {
        return redirect()->route('belanja.baru', ['mode' => 'pwr']);
    }

    /** 7 akaun aset 200-01 (kecuali kod SNT kontra berakhir '5'). */
    private function coaAsetTanpaSnt()
    {
        return Coa::postable()
            ->where('kod', 'like', '200-01%')
            ->where('kod', 'not like', '%5')
            ->orderBy('kod')
            ->get(['id', 'kod', 'nama']);
    }

    /**
     * Cipta row attachment (owner BAYARAN) bagi setiap dokumen[] yang dimuat naik.
     * Lampiran perbelanjaan mengandungi PII (No. KP, alamat, no. akaun) — disimpan
     * PRIVATE & dihidang hanya melalui endpoint berpagar-auth belanja.lampiran().
     */
    private function simpanLampiran(Request $request, Pembayaran $pembayaran): void
    {
        foreach ($this->stashLampiran($request) as $meta) {
            Attachment::create($meta + ['owner_type' => 'BAYARAN', 'owner_id' => $pembayaran->id]);
        }
    }

    /**
     * Simpan fail dokumen[] ke storan PRIVATE (storage/app/private/lampiran) dan pulang
     * METADATA setiap fail (TANPA cipta row attachment). Dikongsi oleh laluan terus
     * (simpanLampiran) DAN laluan maker-checker — pada laluan kelulusan, metadata
     * disimpan dalam payload permohonan & dipautkan kpd pembayaran semasa diluluskan.
     */
    private function stashLampiran(Request $request): array
    {
        $meta = [];
        foreach ((array) $request->file('dokumen', []) as $fail) {
            if (!$fail || !$fail->isValid()) {
                continue;
            }

            $path = $fail->store('lampiran', 'local'); // disk private
            if (!$path) {
                continue; // gagal simpan (cth disk penuh) — jangan rekod metadata palsu
            }

            $meta[] = [
                'file_path'   => $path,
                'file_name'   => $fail->getClientOriginalName(),
                'mime'        => $fail->getClientMimeType(),
                'size_bytes'  => $fail->getSize(),
                'uploaded_by' => app()->bound('current.user_id') ? (int) app('current.user_id') : null,
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        return $meta;
    }

    /**
     * Hidang lampiran perbelanjaan dengan PAGAR auth + skop masjid (route binding
     * Attachment menggunakan global scope BelongsToMasjid → auto-tapis masjid).
     * Header nosniff + Content-Disposition attachment elak MIME-sniffing/inline-XSS.
     */
    public function lampiran(\App\Models\Attachment $lampiran): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(
            in_array($lampiran->owner_type, ['BAYARAN', 'ASET'], true)
            && $lampiran->file_path
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($lampiran->file_path),
            404
        );

        return \Illuminate\Support\Facades\Storage::disk('local')->response(
            $lampiran->file_path,
            $lampiran->file_name ?: basename($lampiran->file_path),
            [
                'Content-Type'           => $lampiran->mime ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition'    => 'attachment; filename="'.addslashes($lampiran->file_name ?: basename($lampiran->file_path)).'"',
            ]
        );
    }
}
