<?php

namespace App\Http\Requests\Belanja;

use App\Http\Requests\BaseFormRequest;
use App\Models\Coa;
use Illuminate\Validation\Validator;

/** Borang Perbelanjaan Bukan Tunai / Jurnal manual (replika belanja_jurnal.php). */
class JurnalRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'tarikh'    => ['required', 'date'],
            'deskripsi' => ['required', 'string', 'max:500'],
            'dr_coa_id' => ['required', 'integer', $this->existsMasjid('coa')],
            'cr_coa_id' => ['required', 'integer', $this->existsMasjid('coa'), 'different:dr_coa_id'],
            'jumlah'    => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * C6 — jurnal manual TIDAK boleh menyentuh akaun TUNAI (bank 250-05xx / PWR 250-06xx).
     * Jika dibenarkan, jurnal mengubah baki tunai tanpa baris kutipan/pembayaran →
     * penyata tunai (buku tunai) menjadi tidak seimbang. Gunakan borang Kutipan/Bayaran/
     * Rekupmen untuk pergerakan tunai.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            foreach (['dr_coa_id' => 'Akaun Debit', 'cr_coa_id' => 'Akaun Kredit'] as $medan => $label) {
                $id = (int) $this->input($medan);
                if (! $id) {
                    continue;
                }
                $kod = Coa::withoutMasjidScope()->whereKey($id)->value('kod');
                if ($kod && (str_starts_with($kod, '250-05') || str_starts_with($kod, '250-06'))) {
                    $v->errors()->add($medan, "$label tidak boleh akaun tunai (bank/PWR) — guna borang Kutipan/Bayaran/Rekupmen.");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'different' => 'Akaun Debit dan Akaun Kredit tidak boleh sama.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tarikh'    => 'Tarikh',
            'deskripsi' => 'Deskripsi',
            'dr_coa_id' => 'Akaun Debit',
            'cr_coa_id' => 'Akaun Kredit',
            'jumlah'    => 'Jumlah',
        ];
    }
}
