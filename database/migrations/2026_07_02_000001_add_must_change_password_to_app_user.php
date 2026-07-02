<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paksa tukar kata laluan pada login pertama (kredensial lalai/reset).
 * Digunakan oleh command sppkms:reset-default-passwords + middleware PaksaTukarKataLaluan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('app_user', 'must_change_password')) {
            return;
        }

        Schema::table('app_user', function (Blueprint $t) {
            $t->boolean('must_change_password')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('app_user', 'must_change_password')) {
            return;
        }

        Schema::table('app_user', function (Blueprint $t) {
            $t->dropColumn('must_change_password');
        });
    }
};
