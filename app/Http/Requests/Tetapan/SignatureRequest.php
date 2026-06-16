<?php

namespace App\Http\Requests\Tetapan;

use App\Http\Requests\BaseFormRequest;

/** Borang Tandatangan Penyata (replika penyata_settings.php). */
class SignatureRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'nama'    => ['required', 'string', 'max:200'],
            'jawatan' => ['required', 'string', 'max:120'],
            'susunan' => ['required', 'integer', 'min:1', 'max:99'],
            'aktif'   => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama'    => 'Nama',
            'jawatan' => 'Jawatan',
            'susunan' => 'Susunan',
            'aktif'   => 'Aktif',
        ];
    }
}
