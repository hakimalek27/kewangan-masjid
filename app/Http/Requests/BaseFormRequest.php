<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Asas semua FormRequest web — mesej validasi Bahasa Melayu seragam.
 * Kebenaran tulis dikawal oleh middleware role:admin,bendahari pada route.
 */
abstract class BaseFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'required'        => 'Medan :attribute wajib diisi.',
            'required_unless' => 'Medan :attribute wajib diisi.',
            'required_if'     => 'Medan :attribute wajib diisi.',
            'accepted'        => 'Sila tandakan :attribute sebelum menghantar.',
            'date'            => 'Medan :attribute mesti tarikh yang sah.',
            'numeric'         => 'Medan :attribute mesti nombor.',
            'integer'         => 'Medan :attribute mesti nombor bulat.',
            'min'             => 'Nilai :attribute terlalu kecil.',
            'max'             => 'Nilai :attribute melebihi had dibenarkan.',
            'in'              => 'Pilihan :attribute tidak sah.',
            'exists'          => 'Pilihan :attribute tidak sah.',
            'array'           => 'Format :attribute tidak sah.',
            'file'            => 'Medan :attribute mesti fail yang sah.',
            'string'          => 'Medan :attribute mesti teks.',
            'boolean'         => 'Medan :attribute tidak sah.',
        ];
    }
}
