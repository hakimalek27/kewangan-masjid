<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ciri "Semak Penyata (AI)" — batch muat naik penyata bank yang diproses AI:
 *  - penyata_semakan       : satu baris per muat naik (kuota, status, kos token)
 *  - bank_statement_line   : + kolum cadangan AI (nullable — laluan CSV tak terjejas)
 * Isolasi tenant: penyata_semakan.masjid_id + BelongsToMasjid pada model.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS penyata_semakan (
              id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
              masjid_id       INT UNSIGNED NOT NULL,
              bank_account_id INT UNSIGNED NOT NULL,
              file_path       VARCHAR(255) NOT NULL,
              original_name   VARCHAR(200) NULL,
              mime            VARCHAR(60) NOT NULL,
              file_hash       CHAR(64) NOT NULL,
              status          ENUM('UPLOADED','AI_PROCESSING','SEDIA','GAGAL') NOT NULL DEFAULT 'UPLOADED',
              provider        VARCHAR(20) NULL,
              model           VARCHAR(80) NULL,
              tokens_used     INT UNSIGNED NULL,
              cost_usd        DECIMAL(10,4) NULL,
              bil_baris       INT UNSIGNED NOT NULL DEFAULT 0,
              bil_auto_padan  INT UNSIGNED NOT NULL DEFAULT 0,
              error_text      VARCHAR(1000) NULL,
              uploaded_by     BIGINT UNSIGNED NULL,
              created_at      TIMESTAMP NULL,
              updated_at      TIMESTAMP NULL,
              UNIQUE KEY uq_ps_hash (masjid_id, file_hash),
              KEY idx_ps_kuota (masjid_id, status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (! Schema::hasColumn('bank_statement_line', 'batch_id')) {
            DB::statement("
                ALTER TABLE bank_statement_line
                  ADD COLUMN batch_id        BIGINT UNSIGNED NULL AFTER imported_at,
                  ADD COLUMN cadangan_jenis  ENUM('KUTIPAN','BAYARAN') NULL AFTER batch_id,
                  ADD COLUMN cadangan_coa_id INT UNSIGNED NULL AFTER cadangan_jenis,
                  ADD COLUMN ai_confidence   TINYINT UNSIGNED NULL AFTER cadangan_coa_id,
                  ADD KEY idx_bsl_batch (batch_id, status)
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bank_statement_line', 'batch_id')) {
            DB::statement('
                ALTER TABLE bank_statement_line
                  DROP KEY idx_bsl_batch,
                  DROP COLUMN batch_id, DROP COLUMN cadangan_jenis,
                  DROP COLUMN cadangan_coa_id, DROP COLUMN ai_confidence
            ');
        }
        DB::statement('DROP TABLE IF EXISTS penyata_semakan');
    }
};
