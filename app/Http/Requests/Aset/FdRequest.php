<?php

namespace App\Http\Requests\Aset;

use App\Http\Requests\BaseFormRequest;

/** Borang FD Baru / Daftar Pelaburan Lama (replika pelaburan_fd_new.php / opening_fd_add.php). */
class FdRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'tarikh'          => ['required', 'date'],
            'institusi'       => ['required', 'string', 'max:200'],
            'coa_fd_id'       => ['required', 'integer', $this->existsMasjid('coa')],
            'bank_account_id' => ['required', 'integer', $this->existsMasjid('bank_account')],
            'jumlah'          => ['required', 'numeric', 'min:0.01'],
            'kadar_pct'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tempoh_bulan'    => ['nullable', 'integer', 'min:1', 'max:600'],
            'maturity_date'   => ['nullable', 'date'],
            'no_sijil'        => ['nullable', 'string', 'max:60'],
            'keterangan'      => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tarikh'          => 'Tarikh Pelaburan',
            'institusi'       => 'Institusi',
            'coa_fd_id'       => 'Akaun FD (COA)',
            'bank_account_id' => 'Bank Sumber',
            'jumlah'          => 'Jumlah Pelaburan',
            'kadar_pct'       => 'Kadar (%)',
            'tempoh_bulan'    => 'Tempoh (bulan)',
            'maturity_date'   => 'Tarikh Matang',
            'no_sijil'        => 'No. Sijil',
            'keterangan'      => 'Keterangan',
        ];
    }
}
