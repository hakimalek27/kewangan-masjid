<?php

namespace App\Http\Requests\Register;

use App\Http\Requests\BaseFormRequest;

/** Borang Daftar Buku Cek (replika daftarBukuCek.php) — register tanpa GL. */
class BukuCekRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'integer', $this->existsMasjid('bank_account')],
            'tarikh_keluar'   => ['required', 'date'],
            'penerima'        => ['required', 'string', 'max:200'],
            'no_siri_mula'    => ['required', 'string', 'max:30'],
            'no_siri_akhir'   => ['required', 'string', 'max:30'],
            'catatan'         => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'bank_account_id' => 'Bank',
            'tarikh_keluar'   => 'Tarikh Keluar',
            'penerima'        => 'Penerima',
            'no_siri_mula'    => 'No. Siri Mula',
            'no_siri_akhir'   => 'No. Siri Akhir',
            'catatan'         => 'Catatan',
        ];
    }
}
