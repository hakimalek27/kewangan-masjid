<?php

namespace App\Http\Requests\Tetapan;

use App\Enums\UserRole;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Borang Pengurusan Pengguna (admin sahaja). Untuk edit, kata laluan
 * adalah pilihan (kosong = kekalkan kata laluan sedia ada).
 */
class PenggunaRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $edit = $this->route('pengguna') !== null;

        return [
            'login' => [
                'required', 'string', 'max:60', 'alpha_dash',
                Rule::unique('app_user', 'login')->ignore($this->route('pengguna')?->id),
            ],
            'nama_penuh'   => ['required', 'string', 'max:200'],
            'role'         => ['required', Rule::enum(UserRole::class)],
            'masjid_id'    => ['required', 'integer', Rule::exists('masjid', 'id')],
            'masjid_ids'   => ['nullable', 'array'],
            'masjid_ids.*' => ['integer', Rule::exists('masjid', 'id')],
            'kata_laluan'  => [$edit ? 'nullable' : 'required', 'string', 'min:6'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'login'        => 'Nama Log Masuk',
            'nama_penuh'   => 'Nama Penuh',
            'role'         => 'Peranan',
            'masjid_id'    => 'Masjid',
            'masjid_ids'   => 'Masjid Ditugaskan',
            'masjid_ids.*' => 'Masjid Ditugaskan',
            'kata_laluan'  => 'Kata Laluan',
            'is_active'    => 'Status Aktif',
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'login.unique'    => 'Nama log masuk ini telah digunakan.',
            'kata_laluan.min' => 'Kata laluan mesti sekurang-kurangnya 6 aksara.',
        ];
    }
}
