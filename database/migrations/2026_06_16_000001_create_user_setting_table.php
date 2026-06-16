<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tetapan PER-PENGGUNA (cth gaya_penyata) — bebas masjid, kekal walau pengguna
 * bertukar masjid (pemerhati/admin). Berasingan dari app_setting (per-masjid).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_setting')) {
            return;
        }

        Schema::create('user_setting', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id');
            $t->string('skey', 80);
            $t->text('svalue')->nullable();
            $t->primary(['user_id', 'skey']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_setting');
    }
};
