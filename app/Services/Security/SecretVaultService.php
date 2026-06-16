<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vault rahsia — kunci API/token disimpan TERSULIT (AES-256, APP_KEY) dalam
 * jadual secret_vault. Jadual lain hanya simpan RUJUKAN (ref), bukan nilai.
 * PENTING: APP_KEY mesti dibackup BERASINGAN daripada dump DB.
 */
class SecretVaultService
{
    /** Simpan rahsia; pulangkan ref untuk disimpan dalam kolum *_ref. */
    public function put(string $plain, ?string $ref = null): string
    {
        $ref ??= 'sec_'.Str::random(24);

        DB::table('secret_vault')->updateOrInsert(
            ['ref' => $ref],
            ['cipher' => Crypt::encryptString($plain), 'rotated_at' => now()]
        );

        return $ref;
    }

    public function get(string $ref): ?string
    {
        $cipher = DB::table('secret_vault')->where('ref', $ref)->value('cipher');

        return $cipher === null ? null : Crypt::decryptString($cipher);
    }

    public function forget(string $ref): void
    {
        DB::table('secret_vault')->where('ref', $ref)->delete();
    }

    /** Untuk paparan UI: tunjuk 4 aksara terakhir sahaja. */
    public function masked(string $ref): ?string
    {
        $plain = $this->get($ref);

        return $plain === null ? null : str_repeat('•', 8).substr($plain, -4);
    }
}
