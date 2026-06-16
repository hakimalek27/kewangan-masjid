<?php

namespace App\Http\Requests\Tetapan;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** Set digit & nombor mula satu siri (replika setResitBaucer.php) — prefix lalai dikekalkan. */
class SiriRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'jenis' => ['required', Rule::in(['RESIT', 'PV', 'PWR', 'JNL'])],
            'digit' => ['required', 'integer', 'min:2', 'max:8'],
            'mula'  => ['required', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'jenis' => 'Jenis Siri',
            'digit' => 'Bilangan Digit',
            'mula'  => 'Nombor Mula',
        ];
    }
}
