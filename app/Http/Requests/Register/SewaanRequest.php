<?php

namespace App\Http\Requests\Register;

use App\Http\Requests\BaseFormRequest;

/** Borang Daftar/Kemaskini Sewaan (replika daftarSewaan.php / editSewaan.php) — register tanpa GL. */
class SewaanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'nama_penyewa'  => ['required', 'string', 'max:200'],
            'no_kp'         => ['nullable', 'string', 'max:30'],
            'no_akaun'      => ['nullable', 'string', 'max:60'],
            'tempoh_sewa'   => ['nullable', 'string', 'max:100'],
            'jenis_sewa'    => ['nullable', 'string', 'max:100'],
            'kadar_sewa'    => ['nullable', 'numeric', 'min:0'],
            'deposit_amaun' => ['nullable', 'numeric', 'min:0'],
            'deposit_resit' => ['nullable', 'string', 'max:60'],
            'catatan'       => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_penyewa'  => 'Nama Penyewa',
            'no_kp'         => 'No. KP',
            'no_akaun'      => 'No. Akaun',
            'tempoh_sewa'   => 'Tempoh Sewa',
            'jenis_sewa'    => 'Jenis Sewa',
            'kadar_sewa'    => 'Kadar Sewa',
            'deposit_amaun' => 'Amaun Deposit',
            'deposit_resit' => 'No. Resit Deposit',
            'catatan'       => 'Catatan',
        ];
    }
}
