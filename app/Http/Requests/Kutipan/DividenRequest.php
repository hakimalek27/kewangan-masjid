<?php

namespace App\Http\Requests\Kutipan;

use App\Http\Requests\BaseFormRequest;

/** Borang Terimaan Dividen/Hibah FD (replika pelaburan_fd_dividen_new.php). */
class DividenRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'fd_id'           => ['required', 'integer', 'exists:fd_investment,id'],
            'coa_id'          => ['required', 'integer', 'exists:coa,id'],
            'jumlah'          => ['required', 'numeric', 'min:0.01'],
            'tarikh'          => ['required', 'date'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_account,id'],
            'auto_resit'      => ['nullable', 'boolean'],
            'no_resit'        => ['nullable', 'required_unless:auto_resit,1', 'string', 'max:30'],
            'deskripsi'       => ['nullable', 'string', 'max:500'],
            'semakan'         => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'fd_id'           => 'Pelaburan (FD)',
            'coa_id'          => 'Akaun Dividen (COA)',
            'jumlah'          => 'Jumlah Dividen',
            'tarikh'          => 'Tarikh Terimaan',
            'bank_account_id' => 'Bank',
            'no_resit'        => 'No. Resit Manual',
            'deskripsi'       => 'Deskripsi',
            'semakan'         => 'Pengesahan Semakan',
        ];
    }
}
