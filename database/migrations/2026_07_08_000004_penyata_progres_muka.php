<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * "Semak Penyata (AI)" — jejak progres pemprosesan (muka demi muka) + kaedah:
 *  - kaedah      : DIGITAL (baca teks terus, pantas) | SCAN (OCR imej) | IMEJ | PDF
 *  - muka_jumlah : jumlah muka penyata
 *  - muka_siap   : muka yang telah diproses (untuk bar progres masa nyata)
 * Membolehkan UI papar "Muka 12/72" berbanding pemberitahuan "1 minit" yang menipu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('penyata_semakan', 'muka_jumlah')) {
            DB::statement("
                ALTER TABLE penyata_semakan
                  ADD COLUMN kaedah      VARCHAR(10)       NULL AFTER model,
                  ADD COLUMN muka_jumlah SMALLINT UNSIGNED NULL AFTER kaedah,
                  ADD COLUMN muka_siap   SMALLINT UNSIGNED NULL AFTER muka_jumlah
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('penyata_semakan', 'muka_jumlah')) {
            DB::statement('
                ALTER TABLE penyata_semakan
                  DROP COLUMN kaedah, DROP COLUMN muka_jumlah, DROP COLUMN muka_siap
            ');
        }
    }
};
