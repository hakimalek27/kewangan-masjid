<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Middleware\Api\ApiScope;
use App\Models\Attachment;
use App\Models\Kutipan;
use App\Models\Pembayaran;
use App\Services\Api\WebhookDispatcher;
use App\Services\Transaksi\KutipanService;
use App\Services\Transaksi\PembayaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /v1/transactions, GET /v1/transactions/{id}, POST /v1/transactions/{id}/void
 * (spec §4 & §5). Transaksi = gabungan kutipan (receipt) + pembayaran (payment)
 * berstatus ACTIVE.
 *
 * KEPUTUSAN REKA BENTUK: id transaksi numerik dikongsi dua jadual — endpoint
 * tunggal & void memerlukan query param `type=receipt|payment` (400 jika tiada).
 */
class TransactionController extends ApiController
{
    public function __construct(
        private KutipanService $kutipan,
        private PembayaranService $pembayaran,
        private WebhookDispatcher $webhook,
    ) {
    }

    /** GET /v1/transactions?from=&to=&type=&coa=&program=&page=&limit= */
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'from'    => ['nullable', 'date_format:Y-m-d'],
            'to'      => ['nullable', 'date_format:Y-m-d'],
            'type'    => ['nullable', 'in:receipt,payment'],
            'coa'     => ['nullable', 'string'],
            'program' => ['nullable', 'string'],
            'page'    => ['nullable', 'integer', 'min:1'],
            'limit'   => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $masjidId = app('current.masjid_id');
        $page  = (int) ($f['page'] ?? 1);
        $limit = (int) ($f['limit'] ?? 50);

        $receipts = DB::table('kutipan as k')
            ->join('coa as c', 'c.id', '=', 'k.coa_id')
            ->where('k.masjid_id', $masjidId)->where('k.status', 'ACTIVE')
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('k.tarikh', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('k.tarikh', '<=', $v))
            ->when($f['coa'] ?? null, fn ($q, $v) => $q->where('c.kod', $v))
            ->when($f['program'] ?? null, fn ($q, $v) => $q->where('k.program', $v))
            ->selectRaw("k.id, 'receipt' as type, k.tarikh as tx_date, k.jumlah as amount,
                c.kod as coa, c.nama as coa_name, k.program, k.nama_pemberi as party,
                k.no_resit as ref_no, k.kaedah as method, k.deskripsi as description");

        $payments = DB::table('pembayaran as p')
            ->join('coa as c', 'c.id', '=', 'p.coa_id')
            ->where('p.masjid_id', $masjidId)->where('p.status', 'ACTIVE')
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('p.tar_lulus', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('p.tar_lulus', '<=', $v))
            ->when($f['coa'] ?? null, fn ($q, $v) => $q->where('c.kod', $v))
            ->when($f['program'] ?? null, fn ($q, $v) => $q->where('p.program', $v))
            ->selectRaw("p.id, 'payment' as type, p.tar_lulus as tx_date, p.jumlah as amount,
                c.kod as coa, c.nama as coa_name, p.program, p.pemohon as party,
                p.baucer_no as ref_no, p.cara_bayar as method, p.deskripsi as description");

        $union = match ($f['type'] ?? null) {
            'receipt' => $receipts,
            'payment' => $payments,
            default   => $receipts->unionAll($payments),
        };

        $total = DB::query()->fromSub($union, 't')->count();
        $rows = DB::query()->fromSub($union, 't')
            ->orderByDesc('tx_date')->orderByDesc('id')
            ->forPage($page, $limit)
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => $this->formatBaris($r))->values(),
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total],
        ]);
    }

    /** GET /v1/transactions/{id}?type=receipt|payment — satu transaksi + jurnal + lampiran */
    public function show(Request $request, int $id): JsonResponse
    {
        $type = $request->query('type');
        if (!in_array($type, ['receipt', 'payment'], true)) {
            return $this->error('VALIDATION_ERROR', 'Query param type=receipt|payment wajib.', 400, 'type');
        }

        if ($type === 'receipt') {
            $rekod = Kutipan::query()->with('coa')->findOrFail($id);
            $detail = [
                'id' => $rekod->id, 'type' => 'receipt',
                'date' => $rekod->tarikh->format('Y-m-d'), 'amount' => (float) $rekod->jumlah,
                'coa' => $rekod->coa->kod, 'coa_name' => $rekod->coa->nama,
                'program' => $rekod->program, 'payer' => $rekod->nama_pemberi,
                'receipt_no' => $rekod->no_resit, 'method' => $this->methodKeluar($rekod->kaedah),
                'description' => $rekod->deskripsi,
                'status' => $rekod->status === 'ACTIVE' ? 'POSTED' : 'VOIDED',
            ];
            $ownerType = 'KUTIPAN';
        } else {
            $rekod = Pembayaran::query()->with('coa')->findOrFail($id);
            $detail = [
                'id' => $rekod->id, 'type' => 'payment',
                'date' => $rekod->tar_lulus->format('Y-m-d'), 'amount' => (float) $rekod->jumlah,
                'coa' => $rekod->coa->kod, 'coa_name' => $rekod->coa->nama,
                'program' => $rekod->program, 'payee' => $rekod->pemohon,
                'voucher_no' => $rekod->baucer_no, 'method' => $rekod->cara_bayar,
                'description' => $rekod->deskripsi,
                'status' => $rekod->status === 'ACTIVE' ? 'POSTED' : 'VOIDED',
            ];
            $ownerType = 'BAYARAN';
        }

        $voucher = $rekod->voucher()->first();
        $detail['voucher_ref'] = $voucher?->voucher_ref;
        $detail['journal'] = $voucher ? $this->journalDariVoucher($voucher) : [];
        $detail['attachments'] = Attachment::query()
            ->where('owner_type', $ownerType)->where('owner_id', $rekod->id)
            ->get()
            ->map(fn ($a) => ['url' => $a->file_path, 'name' => $a->file_name, 'mime' => $a->mime])
            ->values();

        return response()->json($detail);
    }

    /**
     * POST /v1/transactions/{id}/void?type=receipt|payment — VOID, bukan padam
     * (spec §8). Scope: write:receipts (receipt) / write:payments (payment) —
     * disemak di sini kerana bergantung pada jenis.
     */
    public function void(Request $request, int $id): JsonResponse
    {
        $type = $request->query('type', $request->input('type'));
        if (!in_array($type, ['receipt', 'payment'], true)) {
            return $this->error('VALIDATION_ERROR', 'Query param type=receipt|payment wajib.', 400, 'type');
        }

        $scope = $type === 'receipt' ? 'write:receipts' : 'write:payments';
        if (!ApiScope::ada($this->client($request)->scopes, $scope)) {
            return $this->error('FORBIDDEN_SCOPE', "Scope '{$scope}' diperlukan untuk void {$type}.", 403);
        }

        $sebab = (string) $request->input('reason', 'VOID melalui API oleh klien '.$this->client($request)->name);

        if ($type === 'receipt') {
            $rekod = Kutipan::query()->findOrFail($id);
            if ($rekod->status !== 'ACTIVE') {
                return $this->error('DUPLICATE', 'Transaksi telah dibatalkan sebelum ini.', 409);
            }
            $this->kutipan->void($rekod, $sebab);
        } else {
            $rekod = Pembayaran::query()->findOrFail($id);
            if ($rekod->status !== 'ACTIVE') {
                return $this->error('DUPLICATE', 'Transaksi telah dibatalkan sebelum ini.', 409);
            }
            $this->pembayaran->void($rekod, $sebab);
        }

        // Webhook transaction.voided dicetus oleh observer (status → DELETED/CANCELLED)
        // untuk semua asal-usul VOID — tidak diduakan di sini.

        return response()->json(['id' => $rekod->id, 'status' => 'VOIDED']);
    }

    private function formatBaris(object $r): array
    {
        $asas = [
            'id'          => (int) $r->id,
            'type'        => $r->type,
            'date'        => substr((string) $r->tx_date, 0, 10),
            'amount'      => (float) $r->amount,
            'coa'         => $r->coa,
            'coa_name'    => $r->coa_name,
            'program'     => $r->program,
            'description' => $r->description,
        ];

        return $r->type === 'receipt'
            ? [...$asas, 'payer' => $r->party, 'receipt_no' => $r->ref_no, 'method' => $this->methodKeluar($r->method)]
            : [...$asas, 'payee' => $r->party, 'voucher_no' => $r->ref_no, 'method' => $r->method];
    }

    /** Kaedah dalaman → istilah API: BANK_TRANSFER_QR → BANK_TRANSFER. */
    private function methodKeluar(?string $kaedah): ?string
    {
        return $kaedah === 'BANK_TRANSFER_QR' ? 'BANK_TRANSFER' : $kaedah;
    }
}
