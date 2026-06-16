<?php

namespace App\Http\Requests\Belanja;

use App\Http\Requests\BaseFormRequest;

/** Borang Bayaran Perbelanjaan (replika belanja_expense.php, termasuk mode=pwr). */
class BayaranRequest extends BaseFormRequest
{
    /** Normalkan tag program: buang ruang tepi; kosong → null (elak pecahan nota & '' bocor). */
    protected function prepareForValidation(): void
    {
        if ($this->has('program')) {
            $p = trim((string) $this->input('program'));
            $this->merge(['program' => $p === '' ? null : $p]);
        }
    }

    public function rules(): array
    {
        return [
            'tar_mohon'       => ['required', 'date'],
            'tar_lulus'       => ['required', 'date'],
            'no_baucer'       => ['nullable', 'string', 'max:60'],
            'auto_baucer'     => ['nullable', 'boolean'],
            'baucer_no'       => ['nullable', 'required_unless:auto_baucer,1', 'string', 'max:30'],
            'pemohon'         => ['required', 'string', 'max:200'],
            'nokp'            => ['nullable', 'string', 'max:30'],
            'contactno'       => ['nullable', 'string', 'max:30'],
            'alamat'          => ['nullable', 'string', 'max:500'],
            'coa_id'          => ['required', 'integer', $this->existsMasjid('coa')],
            'deskripsi'       => ['nullable', 'string', 'max:500'],
            'program'         => ['nullable', 'string', 'max:120'],
            'jumlah'          => ['required', 'numeric', 'min:0.01'],
            'cara_bayar'      => ['required', 'in:CEK,PWR,EFT,NON_CASH'],
            'bank_account_id' => ['nullable', 'required_unless:cara_bayar,PWR', 'integer', $this->existsMasjid('bank_account')],
            'pwr_coa_id'      => ['nullable', 'required_if:cara_bayar,PWR', 'integer', $this->existsMasjid('coa')],
            'no_cek'          => ['nullable', 'string', 'max:60'],
            'no_acct'         => ['nullable', 'string', 'max:60'],
            'dokumen'         => ['nullable', 'array'],
            'dokumen.*'       => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'semakan'         => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tar_mohon'       => 'Tarikh Mohon',
            'tar_lulus'       => 'Tarikh Lulus',
            'no_baucer'       => 'No. Invois',
            'baucer_no'       => 'No. Baucer Manual',
            'pemohon'         => 'Nama Pemohon/Penerima',
            'nokp'            => 'No. KP',
            'contactno'       => 'No. Telefon',
            'alamat'          => 'Alamat',
            'coa_id'          => 'Jenis Pembayaran (COA)',
            'deskripsi'       => 'Deskripsi',
            'program'         => 'Program',
            'jumlah'          => 'Jumlah Bayaran',
            'cara_bayar'      => 'Cara Pembayaran',
            'bank_account_id' => 'Bank',
            'pwr_coa_id'      => 'Akaun PWR',
            'no_cek'          => 'No. Cek',
            'no_acct'         => 'No. Akaun Penerima',
            'dokumen'         => 'Dokumen Sokongan',
            'dokumen.*'       => 'Dokumen Sokongan',
            'semakan'         => 'Pengesahan Semakan',
        ];
    }
}
