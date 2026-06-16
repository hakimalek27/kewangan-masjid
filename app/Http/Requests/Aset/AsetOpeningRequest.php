<?php

namespace App\Http\Requests\Aset;

use App\Http\Requests\BaseFormRequest;

/** Borang Daftar Aset Lama (replika fixed_asset_opening_balance_add.php) — tiada jurnal. */
class AsetOpeningRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'coa_id'            => ['required', 'integer', $this->existsMasjid('coa')],
            'kod_aset'          => ['nullable', 'string', 'max:40'],
            'nama'              => ['required', 'string', 'max:200'],
            'tarikh_perolehan'  => ['required', 'date'],
            'kos'               => ['required', 'numeric', 'min:0.01'],
            'accumulated_depn'  => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['nullable', 'integer', 'min:1', 'max:99'],
            'depn_rate_pct'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lokasi'            => ['nullable', 'string', 'max:200'],
            'acquisition_type'  => ['required', 'in:BELIAN_LAMA,SUMBANGAN'],
            'donation_value'    => ['nullable', 'numeric', 'min:0'],
            'jenis_aset'        => ['required', 'in:ALIH,TIDAK_ALIH'],
        ];
    }

    public function attributes(): array
    {
        return [
            'coa_id'            => 'Akaun Aset (COA)',
            'kod_aset'          => 'Kod Aset',
            'nama'              => 'Nama Aset',
            'tarikh_perolehan'  => 'Tarikh Perolehan',
            'kos'               => 'Kos',
            'accumulated_depn'  => 'Susut Nilai Terkumpul',
            'useful_life_years' => 'Usia Guna (tahun)',
            'depn_rate_pct'     => 'Kadar Susut Nilai (%)',
            'lokasi'            => 'Lokasi',
            'acquisition_type'  => 'Jenis Perolehan',
            'donation_value'    => 'Nilai Sumbangan',
            'jenis_aset'        => 'Jenis Aset',
        ];
    }
}
