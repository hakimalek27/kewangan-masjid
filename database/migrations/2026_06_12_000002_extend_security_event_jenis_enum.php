<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fasa 7 — tambah jenis security_event untuk pemantauan integriti & backup:
 *   INTEGRITY_FAIL : verify-balance / verify-audit-chain gagal (CRITICAL)
 *   BACKUP_FAIL    : kegagalan backup berulang
 * (Aditif sahaja — nilai sedia ada tidak diubah.)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE security_event MODIFY jenis ENUM(
                'LOGIN_FAIL','MASS_DELETE','ROLE_CHANGE','OFFHOURS_ACCESS',
                'NEW_IP','PERMISSION_DENIED','EXPORT_BULK','CONFIG_CHANGE',
                'INTEGRITY_FAIL','BACKUP_FAIL'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE security_event MODIFY jenis ENUM(
                'LOGIN_FAIL','MASS_DELETE','ROLE_CHANGE','OFFHOURS_ACCESS',
                'NEW_IP','PERMISSION_DENIED','EXPORT_BULK','CONFIG_CHANGE'
            ) NOT NULL
        ");
    }
};
