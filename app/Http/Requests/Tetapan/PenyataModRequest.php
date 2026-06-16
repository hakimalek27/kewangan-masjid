<?php

namespace App\Http\Requests\Tetapan;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** Pilihan paparan penyata — tandatangan atau disclaimer. */
class PenyataModRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'mode'            => ['required', Rule::in(['SIGNATURE', 'DISCLAIMER'])],
            'disclaimer_text' => ['nullable', 'string', 'max:2000', 'required_if:mode,DISCLAIMER'],
        ];
    }

    public function attributes(): array
    {
        return [
            'mode'            => 'Mod Paparan',
            'disclaimer_text' => 'Teks Disclaimer',
        ];
    }
}
