<?php

namespace App\Http\Requests\Tetapan;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** Borang Edit Info Masjid (replika paparMasjid.php). */
class MasjidRequest extends BaseFormRequest
{
    /** Senarai kategori sah (enum jadual masjid). */
    public const KATEGORI = [
        'MASJID KARIAH', 'MASJID INSTITUSI', 'MASJID DAERAH', 'MASJID JAMEK',
        'MASJID NEGERI', 'SURAU JUMAAT', 'SURAU',
    ];

    public function rules(): array
    {
        return [
            'nama'     => ['required', 'string', 'max:200'],
            'kategori' => ['nullable', Rule::in(self::KATEGORI)],
            'alamat'   => ['nullable', 'string', 'max:255'],
            'poskod'   => ['nullable', 'string', 'max:10'],
            'bandar'   => ['nullable', 'string', 'max:100'],
            'daerah'   => ['nullable', 'string', 'max:100'],
            'negeri'   => ['nullable', 'string', 'max:100'],
            'telefon'  => ['nullable', 'string', 'max:30'],
            'fax'      => ['nullable', 'string', 'max:30'],
            'emel'     => ['nullable', 'email', 'max:150'],
            'web'      => ['nullable', 'string', 'max:150'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama'     => 'Nama Masjid',
            'kategori' => 'Kategori',
            'alamat'   => 'Alamat',
            'poskod'   => 'Poskod',
            'bandar'   => 'Bandar',
            'daerah'   => 'Daerah',
            'negeri'   => 'Negeri',
            'telefon'  => 'Telefon',
            'fax'      => 'Fax',
            'emel'     => 'Emel',
            'web'      => 'Laman Web',
        ];
    }
}
