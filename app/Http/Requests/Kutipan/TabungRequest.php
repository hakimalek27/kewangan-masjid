<?php

namespace App\Http\Requests\Kutipan;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Validator;

/** Borang Kutipan Tabung (replika kutipan_tabung_form.php) — 11 baris denominasi tetap. */
class TabungRequest extends BaseFormRequest
{
    /** Denominasi tetap SPPKMS: RM100 → 1 sen (11 baris). */
    public const DENOMINASI = ['100.00', '50.00', '20.00', '10.00', '5.00', '1.00', '0.50', '0.20', '0.10', '0.05', '0.01'];

    public function rules(): array
    {
        return [
            'jenis_tabung'    => ['required', 'in:HARIAN,JUMAAT'],
            'bilangan'        => ['required', 'array'],
            'bilangan.*'      => ['nullable', 'integer', 'min:0'],
            'saksi1'          => ['nullable', 'string', 'max:200'],
            'saksi2'          => ['nullable', 'string', 'max:200'],
            'saksi3'          => ['nullable', 'string', 'max:200'],
            'dibank_oleh'     => ['nullable', 'string', 'max:200'],
            'tar_kira'        => ['required', 'date'],
            'tar_bankin'      => ['nullable', 'date'],
            'bank_account_id' => ['nullable', 'integer', $this->existsMasjid('bank_account')],
            'no_slip'         => ['nullable', 'string', 'max:60'],
            'nama_pemberi'    => ['nullable', 'string', 'max:200'],
            'auto_resit'      => ['nullable', 'boolean'],
            'no_resit'        => ['nullable', 'required_unless:auto_resit,1', 'string', 'max:30'],
            'deskripsi'       => ['nullable', 'string', 'max:500'],
            'semakan'         => ['accepted'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $jumlah = '0.00';
                foreach ((array) $this->input('bilangan', []) as $i => $bil) {
                    $denom = self::DENOMINASI[$i] ?? '0.00';
                    $jumlah = bcadd($jumlah, bcmul($denom, (string) (int) $bil, 2), 2);
                }
                if (bccomp($jumlah, '0', 2) <= 0) {
                    $validator->errors()->add('bilangan', 'Jumlah denominasi mesti lebih daripada sifar.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'jenis_tabung'    => 'Jenis Tabung',
            'bilangan'        => 'Bilangan Denominasi',
            'dibank_oleh'     => 'Dibank Oleh',
            'tar_kira'        => 'Tarikh Kiraan',
            'tar_bankin'      => 'Tarikh Bank Masuk',
            'bank_account_id' => 'Bank',
            'no_slip'         => 'No. Slip Bank',
            'nama_pemberi'    => 'Nama Pemberi',
            'no_resit'        => 'No. Resit Manual',
            'deskripsi'       => 'Deskripsi',
            'semakan'         => 'Pengesahan Semakan',
        ];
    }
}
