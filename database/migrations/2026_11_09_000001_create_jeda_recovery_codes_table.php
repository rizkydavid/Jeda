<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jeda_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash', 64)->unique();       // SHA-256 dari kode; kode asli tidak disimpan
            $table->string('session_key', 100)->index();      // pemegang progres saat ini
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('last_redeemed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jeda_recovery_codes');
    }
};
