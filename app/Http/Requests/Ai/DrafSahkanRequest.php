<?php

namespace App\Http\Requests\Ai;

use App\Http\Requests\BaseFormRequest;

/** Borang pengesahan draf AI — semua medan boleh di-override oleh bendahari. */
class DrafSahkanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'jenis' => ['required', 'in:KUTIPAN,BAYARAN'],
            'tarikh' => ['required', 'date'],
            'jumlah' => ['required', 'numeric', 'min:0.01'],
            'coa_id' => ['required', 'integer', $this->existsMasjid('coa')],
            'penerima' => ['nullable', 'string', 'max:200'],
            'no_rujukan' => ['nullable', 'string', 'max:80'],
            'kaedah' => ['nullable', 'in:EFT,QR,CEK,TUNAI,BANK_TRANSFER_QR'],
            'bank_account_id' => ['nullable', 'integer', $this->existsMasjid('bank_account')],
            'deskripsi' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'jenis' => 'jenis transaksi',
            'tarikh' => 'tarikh',
            'jumlah' => 'jumlah',
            'coa_id' => 'kod akaun',
            'kaedah' => 'kaedah bayaran',
            'bank_account_id' => 'akaun bank',
        ];
    }
}
