<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secret_vault', function (Blueprint $table) {
            $table->string('ref', 120)->primary();
            $table->text('cipher');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('rotated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secret_vault');
    }
};
