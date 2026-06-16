<?php

namespace App\Http\Requests\Tetapan;

use App\Http\Requests\BaseFormRequest;

/** Muat naik logo masjid — PNG/JPG maksimum 2MB. */
class LogoRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return ['logo' => 'Logo Masjid'];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'logo.image' => 'Logo mesti fail imej PNG atau JPG.',
            'logo.mimes' => 'Logo mesti fail imej PNG atau JPG.',
            'logo.max'   => 'Saiz logo melebihi had 2MB.',
        ];
    }
}
