<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Semak Penyata (AI)" — status DIPADAM: batch SIAP (SEDIA) yang fail & datanya
 * dipadam pengguna TETAPI kuota KEKAL dikira (scan sudah digunakan). Ini menutup
 * pepijat "padam → kuota pulih" yang membenarkan pintas had bulanan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE penyata_semakan
              MODIFY status ENUM('UPLOADED','AI_PROCESSING','SEDIA','GAGAL','DIBATAL','DIPADAM')
                     NOT NULL DEFAULT 'UPLOADED'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE penyata_semakan
              MODIFY status ENUM('UPLOADED','AI_PROCESSING','SEDIA','GAGAL','DIBATAL')
                     NOT NULL DEFAULT 'UPLOADED'
        ");
    }
};
