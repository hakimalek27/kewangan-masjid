<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Masjid yang BOLEH DICAPAI oleh seorang pengguna (selain masjid asalnya) —
 * digunakan untuk pemerhati (viewer) yang ditugaskan admin melihat penyata
 * beberapa masjid. Admin melihat semua masjid (tiada baris diperlukan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_masjid')) {
            return;
        }

        Schema::create('user_masjid', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id');
            $t->unsignedInteger('masjid_id');
            $t->primary(['user_id', 'masjid_id']);
            $t->index('masjid_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_masjid');
    }
};
