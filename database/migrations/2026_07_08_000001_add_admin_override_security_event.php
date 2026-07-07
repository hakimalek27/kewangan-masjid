<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Superadmin akses penuh — tambah jenis security_event:
 *   ADMIN_OVERRIDE : admin menulis melalui pagar peranan bukan-admin
 *                    (jejak keselamatan; audit_trail kekal atribusi user_id).
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
                'INTEGRITY_FAIL','BACKUP_FAIL','FUND_DEFICIT','ADMIN_OVERRIDE'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE security_event MODIFY jenis ENUM(
                'LOGIN_FAIL','MASS_DELETE','ROLE_CHANGE','OFFHOURS_ACCESS',
                'NEW_IP','PERMISSION_DENIED','EXPORT_BULK','CONFIG_CHANGE',
                'INTEGRITY_FAIL','BACKUP_FAIL','FUND_DEFICIT'
            ) NOT NULL
        ");
    }
};
