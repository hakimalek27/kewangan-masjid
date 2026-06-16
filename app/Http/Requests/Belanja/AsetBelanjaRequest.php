<?php

namespace App\Http\Requests\Belanja;

use App\Http\Requests\BaseFormRequest;

/** Borang Pembelian Aset (replika belanja_asset.php) — bayar + daftar aset serentak. */
class AsetBelanjaRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'tar_mohon'       => ['required', 'date'],
            'tar_lulus'       => ['required', 'date'],
            'no_baucer'       => ['nullable', 'string', 'max:60'],
            'auto_baucer'     => ['nullable', 'boolean'],
            'baucer_no'       => ['nullable', 'required_unless:auto_baucer,1', 'string', 'max:30'],
            'pemohon'         => ['required', 'string', 'max:200'],
            'nokp'            => ['nullable', 'string', 'max:30'],
            'contactno'       => ['nullable', 'string', 'max:30'],
            'alamat'          => ['nullable', 'string', 'max:500'],
            'coa_id'          => ['required', 'integer', $this->existsMasjid('coa')],
            'asset_name'      => ['required', 'string', 'max:200'],
            'asset_location'  => ['nullable', 'string', 'max:200'],
            'useful_life'     => ['nullable', 'integer', 'min:1', 'max:99'],
            'depn_rate'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deskripsi'       => ['nullable', 'string', 'max:500'],
            'jumlah'          => ['required', 'numeric', 'min:0.01'],
            'cara_bayar'      => ['required', 'in:CEK,EFT'],
            'bank_account_id' => ['required', 'integer', $this->existsMasjid('bank_account')],
            'no_cek'          => ['nullable', 'string', 'max:60'],
            'no_acct'         => ['nullable', 'string', 'max:60'],
            'dokumen'         => ['nullable', 'array'],
            'dokumen.*'       => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'semakan'         => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tar_mohon'       => 'Tarikh Mohon',
            'tar_lulus'       => 'Tarikh Lulus',
            'no_baucer'       => 'No. Invois',
            'baucer_no'       => 'No. Baucer Manual',
            'pemohon'         => 'Nama Pemohon/Penerima',
            'coa_id'          => 'Akaun Aset (COA)',
            'asset_name'      => 'Nama Aset',
            'asset_location'  => 'Lokasi Aset',
            'useful_life'     => 'Usia Guna (tahun)',
            'depn_rate'       => 'Kadar Susut Nilai (%)',
            'deskripsi'       => 'Deskripsi',
            'jumlah'          => 'Jumlah Bayaran',
            'cara_bayar'      => 'Cara Pembayaran',
            'bank_account_id' => 'Bank',
            'no_cek'          => 'No. Cek',
            'no_acct'         => 'No. Akaun Penerima',
            'dokumen'         => 'Dokumen Sokongan',
            'dokumen.*'       => 'Dokumen Sokongan',
            'semakan'         => 'Pengesahan Semakan',
        ];
    }
}
