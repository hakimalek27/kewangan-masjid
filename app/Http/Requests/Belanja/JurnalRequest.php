<?php

namespace App\Http\Requests\Belanja;

use App\Http\Requests\BaseFormRequest;

/** Borang Perbelanjaan Bukan Tunai / Jurnal manual (replika belanja_jurnal.php). */
class JurnalRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'tarikh'    => ['required', 'date'],
            'deskripsi' => ['required', 'string', 'max:500'],
            'dr_coa_id' => ['required', 'integer', $this->existsMasjid('coa')],
            'cr_coa_id' => ['required', 'integer', $this->existsMasjid('coa'), 'different:dr_coa_id'],
            'jumlah'    => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'different' => 'Akaun Debit dan Akaun Kredit tidak boleh sama.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tarikh'    => 'Tarikh',
            'deskripsi' => 'Deskripsi',
            'dr_coa_id' => 'Akaun Debit',
            'cr_coa_id' => 'Akaun Kredit',
            'jumlah'    => 'Jumlah',
        ];
    }
}
