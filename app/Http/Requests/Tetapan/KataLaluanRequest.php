<?php

namespace App\Http\Requests\Tetapan;

use App\Http\Requests\BaseFormRequest;

/** Borang Tukar Kata Laluan (replika doResetPassword.php) — minimum 6 aksara. */
class KataLaluanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'kata_semasa'  => ['required', 'string'],
            'kata_baharu'  => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }

    public function attributes(): array
    {
        return [
            'kata_semasa' => 'Kata Laluan Semasa',
            'kata_baharu' => 'Kata Laluan Baharu',
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'kata_baharu.min'       => 'Kata laluan baharu mesti sekurang-kurangnya 6 aksara.',
            'kata_baharu.confirmed' => 'Pengesahan kata laluan baharu tidak sepadan.',
        ];
    }
}
