<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tetapan PER-PENGGUNA (jadual user_setting, PK user_id+skey) — bebas masjid.
 * Berasingan dari App\Support\Setting (per-masjid). Pilihan kekal walau pengguna
 * bertukar masjid (pemerhati/admin) dan tidak menjejaskan pengguna lain.
 */
class UserSetting
{
    public static function get(string $key, ?string $default = null, ?int $userId = null): ?string
    {
        $userId ??= self::userId();
        if (! $userId) {
            return $default;
        }

        $val = DB::table('user_setting')
            ->where('user_id', $userId)
            ->where('skey', $key)
            ->value('svalue');

        return $val ?? $default;
    }

    public static function set(string $key, ?string $value, ?int $userId = null): void
    {
        $userId ??= self::userId();
        if (! $userId) {
            return;
        }

        DB::table('user_setting')->updateOrInsert(
            ['user_id' => $userId, 'skey' => $key],
            ['svalue' => $value],
        );
    }

    public static function isOn(string $key, ?int $userId = null): bool
    {
        return in_array(self::get($key, 'off', $userId), ['on', '1', 'true'], true);
    }

    private static function userId(): int
    {
        return (int) (auth()->id()
            ?? (app()->bound('current.user_id') ? app('current.user_id') : 0));
    }
}
