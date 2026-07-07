<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tetapan per-masjid (jadual app_setting, PK komposit masjid_id+skey).
 */
class Setting
{
    public static function get(string $key, ?string $default = null, ?int $masjidId = null): ?string
    {
        $masjidId ??= self::masjidId();

        $val = DB::table('app_setting')
            ->where('masjid_id', $masjidId)
            ->where('skey', $key)
            ->value('svalue');

        return $val ?? $default;
    }

    public static function set(string $key, ?string $value, ?int $masjidId = null): void
    {
        $masjidId ??= self::masjidId();

        DB::table('app_setting')->updateOrInsert(
            ['masjid_id' => $masjidId, 'skey' => $key],
            ['svalue' => $value]
        );
    }

    public static function isOn(string $key, ?int $masjidId = null): bool
    {
        return in_array(self::get($key, 'off', $masjidId), ['on', '1', 'true'], true);
    }

    private static function masjidId(): int
    {
        return app()->bound('current.masjid_id')
            ? (int) app('current.masjid_id')
            : (int) config('spkm.masjid_id');
    }
}
