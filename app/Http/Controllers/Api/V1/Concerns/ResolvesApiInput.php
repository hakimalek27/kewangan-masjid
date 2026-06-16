<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\BankAccount;
use App\Models\Coa;
use App\Models\CoaLocalMapping;
use Illuminate\Validation\ValidationException;

/**
 * Resolusi input POST API → entiti dalaman:
 *   - coa (kod '400-03010') ATAU local_label (auto-map via coa_local_mapping)
 *   - bank_slot (1/2/3) → bank_account masjid
 * Gagal → ValidationException (dirender 400 VALIDATION_ERROR, spec §7).
 */
trait ResolvesApiInput
{
    /** @return int coa_id (disahkan boleh-pos & aktif) */
    protected function resolveCoaId(array $data, string $jenisGuna): int
    {
        if (filled($data['coa'] ?? null)) {
            $coa = Coa::query()->postable()->where('kod', $data['coa'])->first();
            if (!$coa) {
                throw ValidationException::withMessages([
                    'coa' => "COA '{$data['coa']}' tidak wujud atau tidak boleh-pos.",
                ]);
            }

            return (int) $coa->id;
        }

        $label = $data['local_label'] ?? null;
        if (blank($label)) {
            throw ValidationException::withMessages([
                'coa' => 'Sila beri "coa" (kod akaun) ATAU "local_label" (label tempatan).',
            ]);
        }

        $map = CoaLocalMapping::query()
            ->where('local_label', $label)
            ->whereIn('jenis_guna', [$jenisGuna, 'kedua'])
            ->first();
        if (!$map) {
            throw ValidationException::withMessages([
                'local_label' => "Label tempatan '{$label}' tiada pemetaan COA ({$jenisGuna}).",
            ]);
        }

        $coa = Coa::query()->postable()->whereKey($map->coa_id)->first();
        if (!$coa) {
            throw ValidationException::withMessages([
                'local_label' => "Pemetaan '{$label}' menuju COA yang tidak boleh-pos/tidak aktif.",
            ]);
        }

        return (int) $coa->id;
    }

    protected function resolveBank(int $slot): BankAccount
    {
        $bank = BankAccount::query()->where('slot', $slot)->where('digunakan', 1)->first();
        if (!$bank) {
            throw ValidationException::withMessages([
                'bank_slot' => "Akaun bank slot {$slot} tidak wujud/tidak digunakan.",
            ]);
        }

        return $bank;
    }
}
