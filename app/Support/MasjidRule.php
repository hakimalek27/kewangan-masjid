<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Pembina peraturan validasi `exists` yang DISKOP kepada masjid semasa — elak rujukan
 * rekod (COA/bank/FD) milik masjid lain (integriti data multi-penyewa).
 * Pemanggil boleh rantai lagi, cth: MasjidRule::exists('coa')->where('is_header', 0).
 */
class MasjidRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        $masjidId = app()->bound('current.masjid_id')
            ? (int) app('current.masjid_id')
            : (int) config('spkm.masjid_id');

        return Rule::exists($table, $column)->where('masjid_id', $masjidId);
    }
}
