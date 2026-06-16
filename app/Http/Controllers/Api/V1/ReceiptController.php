<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiInput;
use App\Models\Attachment;
use App\Services\Api\WebhookDispatcher;
use App\Services\Transaksi\KutipanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /v1/receipts (scope write:receipts) — rekod penerimaan/kutipan
 * melalui KutipanService (jurnal seimbang Dr=Cr dijamin, audit_trail). Spec §5.
 *
 * Pemetaan kaedah API → dalaman: TUNAI→TUNAI, CEK→CEK,
 * BANK_TRANSFER→BANK_TRANSFER_QR (enum jadual kutipan).
 */
class ReceiptController extends ApiController
{
    use ResolvesApiInput;

    public function __construct(
        private KutipanService $kutipan,
        private WebhookDispatcher $webhook,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'           => ['required', 'date_format:Y-m-d'],
            'coa'            => ['required_without:local_label', 'nullable', 'string'],
            'local_label'    => ['nullable', 'string'],
            'amount'         => ['required', 'numeric', 'gt:0'],
            'method'         => ['required', 'in:TUNAI,CEK,BANK_TRANSFER'],
            'bank_slot'      => ['required_unless:method,TUNAI', 'nullable', 'integer'],
            'payer'          => ['nullable', 'string', 'max:200'],
            'receipt_no'     => ['required', 'string', 'max:40'],
            'program'        => ['nullable', 'string', 'max:120'],
            'description'    => ['nullable', 'string', 'max:500'],
            'attachment_url' => ['nullable', 'url', 'max:255'],
        ]);

        $coaId = $this->resolveCoaId($data, 'penerimaan');
        $bank = isset($data['bank_slot']) && $data['method'] !== 'TUNAI'
            ? $this->resolveBank((int) $data['bank_slot'])
            : null;

        $auto = strtolower($data['receipt_no']) === 'auto';

        $kutipan = $this->kutipan->create([
            'jenis'           => 'BIASA',
            'tarikh'          => $data['date'],
            'coa_id'          => $coaId,
            'kaedah'          => $data['method'] === 'BANK_TRANSFER' ? 'BANK_TRANSFER_QR' : $data['method'],
            'jumlah'          => number_format((float) $data['amount'], 2, '.', ''),
            'auto_resit'      => $auto,
            'no_resit'        => $auto ? null : $data['receipt_no'],
            'nama_pemberi'    => $data['payer'] ?? null,
            'bank_account_id' => $bank?->id,
            'program'         => $data['program'] ?? null,
            'deskripsi'       => $data['description'] ?? null,
        ]);

        if (filled($data['attachment_url'] ?? null)) {
            Attachment::create([
                'owner_type' => 'KUTIPAN',
                'owner_id'   => $kutipan->id,
                'file_path'  => $data['attachment_url'],
                'file_name'  => basename(parse_url($data['attachment_url'], PHP_URL_PATH) ?: 'lampiran'),
            ]);
        }

        $voucher = $kutipan->voucher()->first();

        // Webhook transaction.created dicetus oleh KutipanObserver (satu seam
        // untuk semua asal-usul: web/API/draf) — tidak diduakan di sini.

        return response()->json([
            'id'          => $kutipan->id,
            'voucher_ref' => $voucher->voucher_ref,
            'status'      => 'POSTED',
            'journal'     => $this->journalDariVoucher($voucher),
            'receipt_no'  => $kutipan->no_resit,
        ], 201);
    }
}
