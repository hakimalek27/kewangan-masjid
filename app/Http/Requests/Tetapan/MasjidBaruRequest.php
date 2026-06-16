<?php

namespace App\Http\Requests\Tetapan;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Borang Masjid Baru (Phase B, admin sahaja) — cipta rekod masjid + login bendahari
 * pertama dalam satu transaksi. Tiada penyemaian COA/bank (bendahari guna Wizard).
 */
class MasjidBaruRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // --- Profil masjid ---
            'nama'     => ['required', 'string', 'max:200'],
            'kategori' => ['nullable', Rule::in(MasjidRequest::KATEGORI)],
            'alamat'   => ['nullable', 'string', 'max:255'],
            'poskod'   => ['nullable', 'string', 'max:10'],
            'bandar'   => ['nullable', 'string', 'max:100'],
            'daerah'   => ['nullable', 'string', 'max:100'],
            'negeri'   => ['nullable', 'string', 'max:100'],
            'telefon'  => ['nullable', 'string', 'max:30'],
            'emel'     => ['nullable', 'email', 'max:150'],
            // --- Login bendahari pertama (global-unique) ---
            'login'       => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('app_user', 'login')],
            'nama_penuh'  => ['required', 'string', 'max:200'],
            'kata_laluan' => ['required', 'string', 'min:6'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama'        => 'Nama Masjid',
            'kategori'    => 'Kategori',
            'alamat'      => 'Alamat',
            'poskod'      => 'Poskod',
            'bandar'      => 'Bandar',
            'daerah'      => 'Daerah',
            'negeri'      => 'Negeri',
            'telefon'     => 'Telefon',
            'emel'        => 'Emel',
            'login'       => 'Nama Log Masuk Bendahari',
            'nama_penuh'  => 'Nama Penuh Bendahari',
            'kata_laluan' => 'Kata Laluan',
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'login.unique' => 'Nama log masuk ini telah digunakan.',
            'login.alpha_dash' => 'Nama log masuk hanya huruf, nombor, sengkang dan garis bawah.',
        ];
    }
}
