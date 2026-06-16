<?php

namespace App\Http\Requests\Register;

use App\Http\Requests\BaseFormRequest;

/** Borang Daftar Cek Batal (replika daftarBukuCekBatal.php) — register tanpa GL. */
class CekBatalRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'no_cek_batal'    => ['required', 'string', 'max:30'],
            'tarikh_batal'    => ['required', 'date'],
            'penerima'        => ['nullable', 'string', 'max:200'],
            'amaun'           => ['required', 'numeric', 'min:0'],
            'sebab_batal'     => ['nullable', 'string', 'max:500'],
            'pengesahan_oleh' => ['nullable', 'string', 'max:200'],
            'no_cek_ganti'    => ['nullable', 'string', 'max:30'],
            'tarikh_ganti'    => ['nullable', 'date'],
            'catatan'         => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'no_cek_batal'    => 'No. Cek Batal',
            'tarikh_batal'    => 'Tarikh Batal',
            'penerima'        => 'Penerima',
            'amaun'           => 'Amaun',
            'sebab_batal'     => 'Sebab Batal',
            'pengesahan_oleh' => 'Pengesahan Oleh',
            'no_cek_ganti'    => 'No. Cek Ganti',
            'tarikh_ganti'    => 'Tarikh Ganti',
            'catatan'         => 'Catatan',
        ];
    }
}
