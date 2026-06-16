<?php

namespace App\Http\Requests\Kutipan;

use App\Http\Requests\BaseFormRequest;

/** Borang Kutipan Baru (replika kutipan_form.php). */
class KutipanRequest extends BaseFormRequest
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
            'coa_id'          => ['required', 'integer', $this->existsMasjid('coa')],
            'kaedah'          => ['required', 'in:TUNAI,CEK,BANK_TRANSFER_QR'],
            'tarikh'          => ['required', 'date'],
            'jumlah'          => ['required', 'numeric', 'min:0.01'],
            'auto_resit'      => ['nullable', 'boolean'],
            'no_resit'        => ['nullable', 'required_unless:auto_resit,1', 'string', 'max:30'],
            'nama_pemberi'    => ['nullable', 'string', 'max:200'],
            'saksi1'          => ['nullable', 'string', 'max:200'],
            'saksi2'          => ['nullable', 'string', 'max:200'],
            'saksi3'          => ['nullable', 'string', 'max:200'],
            'bank_account_id' => ['nullable', 'required_unless:kaedah,TUNAI', 'integer', $this->existsMasjid('bank_account')],
            'no_slip'         => ['nullable', 'string', 'max:60'],
            'tar_bankin'      => ['nullable', 'date'],
            'deskripsi'       => ['nullable', 'string', 'max:500'],
            'program'         => ['nullable', 'string', 'max:120'],
            'semakan'         => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'coa_id'          => 'Jenis Kutipan (COA)',
            'kaedah'          => 'Kaedah Kutipan',
            'tarikh'          => 'Tarikh Kutipan',
            'jumlah'          => 'Jumlah Kutipan',
            'no_resit'        => 'No. Resit Manual',
            'nama_pemberi'    => 'Nama Pemberi',
            'bank_account_id' => 'Bank',
            'no_slip'         => 'No. Slip Bank',
            'tar_bankin'      => 'Tarikh Bank Masuk',
            'deskripsi'       => 'Deskripsi',
            'program'         => 'Program',
            'semakan'         => 'Pengesahan Semakan',
        ];
    }
}
