<?php

namespace App\Http\Requests\Tetapan;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** Borang Set Baki Awal (replika opening_balance.php) — baris dinamik COA/Amaun/Sisi. */
class OpeningBalanceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $masjidId = app()->bound('current.masjid_id') ? app('current.masjid_id') : config('sppkms.masjid_id');

        return [
            'tahun'          => ['required', 'integer', 'min:2000', 'max:2100'],
            'rows'           => ['required', 'array', 'min:1'],
            'rows.*.coa_id'  => [
                'required', 'integer',
                Rule::exists('coa', 'id')->where('masjid_id', $masjidId)->where('is_header', 0),
            ],
            'rows.*.amaun'   => ['required', 'numeric', 'min:0.01'],
            'rows.*.side'    => ['required', Rule::in(['D', 'C'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'tahun'         => 'Tahun',
            'rows'          => 'Baris Baki Awal',
            'rows.*.coa_id' => 'Kod Akaun',
            'rows.*.amaun'  => 'Amaun',
            'rows.*.side'   => 'Sisi (D/C)',
        ];
    }
}
