<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fasa 9 — Perakaunan Lanjutan:
 *   1. approval.entity_id NULLABLE — permohonan maker-checker dicipta SEBELUM
 *      rekod pembayaran wujud (entity_id diisi selepas diluluskan).
 *   2. approval.payload (MEDIUMTEXT) — payload borang penuh (JSON) untuk
 *      dimainkan semula oleh PembayaranService apabila diluluskan
 *      (remark VARCHAR(300) terlalu kecil untuk JSON penuh).
 *   3. security_event.jenis + 'FUND_DEFICIT' — amaran defisit dana/tabung.
 *   4. depreciation_schedule UNIQUE (fixed_asset_id, tahun, bulan) —
 *      jaminan idempoten susut nilai bulanan di peringkat DB.
 * (Aditif sahaja — tiada data sedia ada diubah.)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE approval MODIFY entity_id BIGINT UNSIGNED NULL');

        if (!Schema::hasColumn('approval', 'payload')) {
            DB::statement('ALTER TABLE approval ADD COLUMN payload MEDIUMTEXT NULL AFTER remark');
        }

        DB::statement("
            ALTER TABLE security_event MODIFY jenis ENUM(
                'LOGIN_FAIL','MASS_DELETE','ROLE_CHANGE','OFFHOURS_ACCESS',
                'NEW_IP','PERMISSION_DENIED','EXPORT_BULK','CONFIG_CHANGE',
                'INTEGRITY_FAIL','BACKUP_FAIL','FUND_DEFICIT'
            ) NOT NULL
        ");

        $adaIndeks = DB::select("
            SELECT 1 FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'depreciation_schedule'
              AND index_name = 'uq_dep_bulan' LIMIT 1
        ");
        if (!$adaIndeks) {
            DB::statement('ALTER TABLE depreciation_schedule ADD UNIQUE KEY uq_dep_bulan (fixed_asset_id, tahun, bulan)');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE depreciation_schedule DROP INDEX uq_dep_bulan');
        DB::statement("
            ALTER TABLE security_event MODIFY jenis ENUM(
                'LOGIN_FAIL','MASS_DELETE','ROLE_CHANGE','OFFHOURS_ACCESS',
                'NEW_IP','PERMISSION_DENIED','EXPORT_BULK','CONFIG_CHANGE',
                'INTEGRITY_FAIL','BACKUP_FAIL'
            ) NOT NULL
        ");
        DB::statement('ALTER TABLE approval DROP COLUMN payload');
        DB::statement('ALTER TABLE approval MODIFY entity_id BIGINT UNSIGNED NOT NULL');
    }
};
