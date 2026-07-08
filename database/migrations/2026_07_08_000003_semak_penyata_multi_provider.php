<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-provider "Semak Penyata (AI)" — superadmin simpan beberapa profil provider
 * (OpenAI/DeepSeek/Ollama/OpenRouter/custom, semua serasi-OpenAI via base_url) dan
 * bendahari pilih provider semasa muat naik supaya boleh BANDING kualiti OCR.
 *
 *  - Jadual `sp_provider`: profil provider GLOBAL (dikawal superadmin; kunci di vault).
 *  - `penyata_semakan.sp_provider_id` + `provider_label`: rekod provider mana yang scan
 *    batch itu (0 = legasi/lalai). Indeks unik dedup jadi (masjid_id, file_hash,
 *    sp_provider_id) supaya penyata SAMA boleh discan sekali PER provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_provider', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 80);
            $table->string('dialect', 20)->default('openai'); // openai = serasi OpenAI (OpenAI/DeepSeek/Ollama/OpenRouter)
            $table->string('base_url', 200)->nullable();       // kosong = https://api.openai.com
            $table->string('model', 80);
            $table->string('api_key_ref', 60)->nullable();     // rujukan secret_vault
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('catatan', 200)->nullable();
            $table->timestamps();
        });

        Schema::table('penyata_semakan', function (Blueprint $table) {
            $table->unsignedBigInteger('sp_provider_id')->default(0)->after('bank_account_id');
            $table->string('provider_label', 120)->nullable()->after('sp_provider_id');
        });

        // Dedup kini per-provider: penyata sama boleh discan sekali setiap provider.
        Schema::table('penyata_semakan', function (Blueprint $table) {
            $table->dropUnique('uq_ps_hash');
            $table->unique(['masjid_id', 'file_hash', 'sp_provider_id'], 'uq_ps_hash');
        });
    }

    public function down(): void
    {
        Schema::table('penyata_semakan', function (Blueprint $table) {
            $table->dropUnique('uq_ps_hash');
            $table->unique(['masjid_id', 'file_hash'], 'uq_ps_hash');
            $table->dropColumn(['sp_provider_id', 'provider_label']);
        });

        Schema::dropIfExists('sp_provider');
    }
};
