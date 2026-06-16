<?php

namespace App\Http\Requests\Belanja;

use App\Http\Requests\BaseFormRequest;

/** Borang Rekupmen PWR (replika pwr_rekupmen.php) — pemindahan Bank → PWR. */
class RekupmenRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'tar_mohon'       => ['required', 'date'],
            'tar_lulus'       => ['required', 'date'],
            'auto_baucer'     => ['nullable', 'boolean'],
            'baucer_no'       => ['nullable', 'required_unless:auto_baucer,1', 'string', 'max:30'],
            'pwr_coa_id'      => ['required', 'integer', $this->existsMasjid('coa')],
            'bank_account_id' => ['required', 'integer', $this->existsMasjid('bank_account')],
            'jumlah'          => ['required', 'numeric', 'min:0.01'],
            'pemohon'         => ['nullable', 'string', 'max:200'],
            'deskripsi'       => ['nullable', 'string', 'max:500'],
            'cara_bayar'      => ['required', 'in:EFT,CEK'],
            'no_cek'          => ['nullable', 'string', 'max:60'],
            'semakan'         => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tar_mohon'       => 'Tarikh Mohon',
            'tar_lulus'       => 'Tarikh Lulus',
            'baucer_no'       => 'No. Baucer Manual',
            'pwr_coa_id'      => 'Akaun PWR',
            'bank_account_id' => 'Bank',
            'jumlah'          => 'Jumlah Rekupmen',
            'pemohon'         => 'Pemohon',
            'deskripsi'       => 'Deskripsi',
            'cara_bayar'      => 'Cara Pembayaran',
            'no_cek'          => 'No. Cek',
            'semakan'         => 'Pengesahan Semakan',
        ];
    }
}
