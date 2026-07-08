<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Semak Penyata (AI)" — simpan JUMLAH BESAR (Grand Total Debit/Credit) yang
 * TERCETAK dalam penyata digital, untuk semakan TALLY automatik terhadap jumlah
 * baris yang diekstrak (parse deterministik). Membuktikan ekstraksi lengkap.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('penyata_semakan', 'penyata_jum_debit')) {
            DB::statement('
                ALTER TABLE penyata_semakan
                  ADD COLUMN penyata_jum_debit  DECIMAL(16,2) NULL AFTER completion_tokens,
                  ADD COLUMN penyata_jum_kredit DECIMAL(16,2) NULL AFTER penyata_jum_debit
            ');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('penyata_semakan', 'penyata_jum_debit')) {
            DB::statement('ALTER TABLE penyata_semakan DROP COLUMN penyata_jum_debit, DROP COLUMN penyata_jum_kredit');
        }
    }
};
