<?php

namespace App\Http\Requests\Tetapan;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** Borang Set Kod Penerimaan/Perbelanjaan (replika setup_mapping.php). */
class MappingRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $masjidId = app()->bound('current.masjid_id') ? app('current.masjid_id') : config('sppkms.masjid_id');
        $mappingId = $this->route('mapping')?->id;

        return [
            'local_label' => [
                'required', 'string', 'max:150',
                Rule::unique('coa_local_mapping', 'local_label')
                    ->where('masjid_id', $masjidId)
                    ->where('jenis_guna', (string) $this->input('jenis_guna'))
                    ->ignore($mappingId),
            ],
            'jenis_guna' => ['required', Rule::in(['penerimaan', 'perbelanjaan', 'kedua'])],
            'coa_id'     => [
                'required', 'integer',
                Rule::exists('coa', 'id')->where('masjid_id', $masjidId)->where('is_header', 0),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'local_label' => 'Label Tempatan',
            'jenis_guna'  => 'Jenis Guna',
            'coa_id'      => 'Kod Akaun (COA)',
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'local_label.unique' => 'Label tempatan ini telah wujud bagi jenis guna yang sama.',
        ];
    }
}
