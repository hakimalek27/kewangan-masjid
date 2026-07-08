<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Semak Penyata (AI)" — pantauan kos TEPAT untuk superadmin:
 *  - penyata_semakan.prompt_tokens/completion_tokens : pecahan token (input/output)
 *    daripada `usage` respons AI (autoritatif) → kos dikira ikut kadar berasingan.
 *  - sp_provider.kos_input_1k/kos_output_1k : kadar USD/1000 token setiap provider
 *    (input & output biasanya harga berbeza). Kosong → jatuh ke kadar pukul-rata
 *    global (sp_kos_per_1k_usd).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('penyata_semakan', 'prompt_tokens')) {
            DB::statement('
                ALTER TABLE penyata_semakan
                  ADD COLUMN prompt_tokens     INT UNSIGNED NULL AFTER tokens_used,
                  ADD COLUMN completion_tokens INT UNSIGNED NULL AFTER prompt_tokens
            ');
        }

        if (Schema::hasTable('sp_provider') && ! Schema::hasColumn('sp_provider', 'kos_input_1k')) {
            DB::statement('
                ALTER TABLE sp_provider
                  ADD COLUMN kos_input_1k  DECIMAL(12,6) NULL AFTER model,
                  ADD COLUMN kos_output_1k DECIMAL(12,6) NULL AFTER kos_input_1k
            ');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('penyata_semakan', 'prompt_tokens')) {
            DB::statement('ALTER TABLE penyata_semakan DROP COLUMN prompt_tokens, DROP COLUMN completion_tokens');
        }
        if (Schema::hasTable('sp_provider') && Schema::hasColumn('sp_provider', 'kos_input_1k')) {
            DB::statement('ALTER TABLE sp_provider DROP COLUMN kos_input_1k, DROP COLUMN kos_output_1k');
        }
    }
};
