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
    public function authorize(): bool
    {
        $pengguna = $this->route('pengguna');
        if (! $pengguna || $this->user()?->isAdmin()) {
            return true;
        }

        // Bukan-admin (bendahari): hanya urus pengguna masjid SENDIRI & BUKAN akaun admin (anti-IDOR).
        return (int) $pengguna->masjid_id === (int) app('current.masjid_id')
            && $pengguna->role !== UserRole::ADMIN;
    }

    public function rules(): array
    {
        $edit = $this->route('pengguna') !== null;
        $isAdmin = (bool) $this->user()?->isAdmin();

        return [
            'login' => [
                'required', 'string', 'max:60', 'alpha_dash',
                Rule::unique('app_user', 'login')->ignore($this->route('pengguna')?->id),
            ],
            'nama_penuh'   => ['required', 'string', 'max:200'],
            // Bukan-admin TIDAK boleh melantik peranan 'admin'.
            'role'         => ['required', Rule::in($this->perananDibenarkan())],
            // Admin pilih masjid; bukan-admin dipaksa ke masjid semasa dalam controller.
            'masjid_id'    => $isAdmin ? ['required', 'integer', Rule::exists('masjid', 'id')] : ['nullable', 'integer'],
            'masjid_ids'   => ['nullable', 'array'],
            'masjid_ids.*' => ['integer', Rule::exists('masjid', 'id')],
            'kata_laluan'  => [$edit ? 'nullable' : 'required', 'string', 'min:6'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    /**
     * Peranan yang pemohon dibenarkan melantik — model SaaS: hanya peranan
     * DITAWARKAN (admin/bendahari/juruaudit/viewer; bukan-admin tanpa 'admin').
     * Semasa EDIT, peranan LEGASI semasa akaun itu turut diterima supaya
     * akaun lama (pentadbir/pengerusi/setiausaha) boleh dikemas kini tanpa
     * dipaksa tukar peranan.
     */
    private function perananDibenarkan(): array
    {
        $cases = $this->user()?->isAdmin()
            ? UserRole::ditawarkan()
            : array_filter(UserRole::ditawarkan(), fn (UserRole $r) => $r !== UserRole::ADMIN);

        $nilai = array_map(fn (UserRole $r) => $r->value, $cases);

        $sediaAda = $this->route('pengguna')?->role?->value;
        if ($sediaAda !== null && ! in_array($sediaAda, $nilai, true)) {
            $nilai[] = $sediaAda;
        }

        return $nilai;
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
