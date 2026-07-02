<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * E13 — v_program_report tidak menapis status → akan mengira kutipan DELETED /
 * pembayaran CANCELLED (void). View ini TIDAK digunakan oleh aplikasi (ReportService
 * guna query tapisan-POSTED sendiri), tetapi dibetulkan supaya betul jika di-query terus.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW v_program_report AS
            SELECT x.masjid_id, x.program,
                   SUM(x.terima) AS terima, SUM(x.belanja) AS belanja,
                   SUM(x.terima) - SUM(x.belanja) AS net
            FROM (
                SELECT masjid_id, program, jumlah AS terima, 0 AS belanja
                FROM kutipan WHERE program IS NOT NULL AND status = 'ACTIVE'
                UNION ALL
                SELECT masjid_id, program, 0 AS terima, jumlah AS belanja
                FROM pembayaran WHERE program IS NOT NULL AND status = 'ACTIVE'
            ) x
            GROUP BY x.masjid_id, x.program
        ");
    }

    public function down(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW v_program_report AS
            SELECT x.masjid_id, x.program,
                   SUM(x.terima) AS terima, SUM(x.belanja) AS belanja,
                   SUM(x.terima) - SUM(x.belanja) AS net
            FROM (
                SELECT masjid_id, program, jumlah AS terima, 0 AS belanja
                FROM kutipan WHERE program IS NOT NULL
                UNION ALL
                SELECT masjid_id, program, 0 AS terima, jumlah AS belanja
                FROM pembayaran WHERE program IS NOT NULL
            ) x
            GROUP BY x.masjid_id, x.program
        ");
    }
};
