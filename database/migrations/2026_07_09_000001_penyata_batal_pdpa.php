<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Semak Penyata (AI)" — sokong BATAL semasa proses + bukti persetujuan PDPA:
 *  - status DIBATAL  : batch dibatalkan pengguna semasa AI memproses
 *  - batal_diminta    : bendera diminta batal (job semak antara muka → berhenti)
 *  - pdpa_setuju_oleh/pada : bukti tenant SETUJU kongsi penyata bank (PDPA) semasa
 *    muat naik — melindungi penyedia jika berlaku pertikaian data peribadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE penyata_semakan
              MODIFY status ENUM('UPLOADED','AI_PROCESSING','SEDIA','GAGAL','DIBATAL') NOT NULL DEFAULT 'UPLOADED'
        ");

        if (! Schema::hasColumn('penyata_semakan', 'batal_diminta')) {
            DB::statement("
                ALTER TABLE penyata_semakan
                  ADD COLUMN batal_diminta    TINYINT(1)      NOT NULL DEFAULT 0 AFTER status,
                  ADD COLUMN pdpa_setuju_oleh  BIGINT UNSIGNED NULL AFTER uploaded_by,
                  ADD COLUMN pdpa_setuju_pada  TIMESTAMP       NULL AFTER pdpa_setuju_oleh
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('penyata_semakan', 'batal_diminta')) {
            DB::statement('
                ALTER TABLE penyata_semakan
                  DROP COLUMN batal_diminta, DROP COLUMN pdpa_setuju_oleh, DROP COLUMN pdpa_setuju_pada
            ');
        }
        DB::statement("
            ALTER TABLE penyata_semakan
              MODIFY status ENUM('UPLOADED','AI_PROCESSING','SEDIA','GAGAL') NOT NULL DEFAULT 'UPLOADED'
        ");
    }
};
