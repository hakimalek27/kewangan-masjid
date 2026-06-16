<?php

namespace App\Http\Requests\Register;

use App\Http\Requests\BaseFormRequest;

/** Borang Daftar Peti Besi (replika daftarPetiBesi.php) — register tanpa GL. */
class PetiBesiRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'perkara'       => ['required', 'string', 'max:300'],
            'nama_masuk'    => ['required', 'string', 'max:200'],
            'tarikh_masuk'  => ['required', 'date'],
            'nama_keluar'   => ['nullable', 'string', 'max:200'],
            'tarikh_keluar' => ['nullable', 'date'],
            'catatan'       => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'perkara'       => 'Perkara',
            'nama_masuk'    => 'Nama Pemasuk',
            'tarikh_masuk'  => 'Tarikh Masuk',
            'nama_keluar'   => 'Nama Pengeluar',
            'tarikh_keluar' => 'Tarikh Keluar',
            'catatan'       => 'Catatan',
        ];
    }
}
