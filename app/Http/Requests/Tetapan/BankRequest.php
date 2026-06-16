<?php

namespace App\Http\Requests\Tetapan;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** Borang Tambah/Edit Bank (replika bankinfo_list.php) — maksimum 3 slot. */
class BankRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $masjidId = app()->bound('current.masjid_id') ? app('current.masjid_id') : config('sppkms.masjid_id');
        $bankId = $this->route('bank')?->id;

        return [
            'slot' => [
                'required', 'integer', 'min:1', 'max:3',
                Rule::unique('bank_account', 'slot')
                    ->where('masjid_id', $masjidId)
                    ->ignore($bankId),
            ],
            'nama_bank' => ['required', 'string', 'max:120'],
            'no_akaun'  => ['required', 'string', 'max:40'],
            'coa_id'    => ['required', 'integer', $this->existsMasjid('coa')],
            'status'    => ['required', Rule::in(['AKTIF', 'TIDAK AKTIF'])],
            'digunakan' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'slot'      => 'Slot',
            'nama_bank' => 'Nama Bank',
            'no_akaun'  => 'No. Akaun',
            'coa_id'    => 'Kod Akaun (COA)',
            'status'    => 'Status',
            'digunakan' => 'Penggunaan',
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'slot.unique' => 'Slot ini telah digunakan oleh bank lain. Sila edit rekod slot berkenaan.',
        ];
    }
}
