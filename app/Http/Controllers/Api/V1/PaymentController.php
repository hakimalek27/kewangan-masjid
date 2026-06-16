<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiInput;
use App\Models\Coa;
use App\Services\Api\WebhookDispatcher;
use App\Services\Transaksi\PembayaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /v1/payments (scope write:payments) — rekod perbelanjaan/pembayaran
 * melalui PembayaranService::createBayaran (jurnal Dr belanja / Cr bank|PWR).
 * Spec §5. method: EFT|CEK (perlu bank_slot) atau PWR (perlu pwr_coa).
 */
class PaymentController extends ApiController
{
    use ResolvesApiInput;

    public function __construct(
        private PembayaranService $pembayaran,
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
            'method'         => ['required', 'in:EFT,CEK,PWR'],
            'bank_slot'      => ['required_unless:method,PWR', 'nullable', 'integer'],
            'pwr_coa'        => ['required_if:method,PWR', 'nullable', 'string'],
            'payee'          => ['nullable', 'string', 'max:200'],
            'voucher_no'     => ['required', 'string', 'max:40'],
            'program'        => ['nullable', 'string', 'max:120'],
            'description'    => ['nullable', 'string', 'max:500'],
            'attachment_url' => ['nullable', 'url', 'max:255'],
        ]);

        $coaId = $this->resolveCoaId($data, 'perbelanjaan');
        $auto = strtolower($data['voucher_no']) === 'auto';

        $input = [
            'tar_mohon'   => $data['date'],
            'tar_lulus'   => $data['date'],
            'coa_id'      => $coaId,
            'jumlah'      => number_format((float) $data['amount'], 2, '.', ''),
            'cara_bayar'  => $data['method'],
            'pemohon'     => $data['payee'] ?? null,
            'auto_baucer' => $auto,
            'baucer_no'   => $auto ? null : $data['voucher_no'],
            'program'     => $data['program'] ?? null,
            'deskripsi'   => $data['description'] ?? null,
        ];

        if ($data['method'] === 'PWR') {
            $pwr = Coa::query()->postable()->where('kod', $data['pwr_coa'])->first();
            if (!$pwr || !str_starts_with($pwr->kod, '250-06')) {
                throw ValidationException::withMessages([
                    'pwr_coa' => "pwr_coa mesti akaun PWR sah (250-060x0), diberi '{$data['pwr_coa']}'.",
                ]);
            }
            $input['pwr_coa_id'] = $pwr->id;
        } else {
            $input['bank_account_id'] = $this->resolveBank((int) $data['bank_slot'])->id;
        }

        $bayaran = $this->pembayaran->createBayaran($input);

        if (filled($data['attachment_url'] ?? null)) {
            \App\Models\Attachment::create([
                'owner_type' => 'BAYARAN',
                'owner_id'   => $bayaran->id,
                'file_path'  => $data['attachment_url'],
                'file_name'  => basename(parse_url($data['attachment_url'], PHP_URL_PATH) ?: 'lampiran'),
            ]);
        }

        $voucher = $bayaran->voucher()->first();

        // Webhook transaction.created dicetus oleh PembayaranObserver (satu seam) —
        // tidak diduakan di sini.

        return response()->json([
            'id'          => $bayaran->id,
            'voucher_ref' => $voucher->voucher_ref,
            'status'      => 'POSTED',
            'journal'     => $this->journalDariVoucher($voucher),
            'voucher_no'  => $bayaran->baucer_no,
        ], 201);
    }
}
