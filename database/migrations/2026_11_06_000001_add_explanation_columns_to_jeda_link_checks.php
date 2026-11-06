<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jeda_link_checks', function (Blueprint $table) {
            // Token acak sebagai "kunci" untuk meminta penjelasan atas hasil ini (id urut mudah ditebak).
            $table->string('token', 40)->nullable()->unique()->after('session_key');
            // Ringkasan temuan (id, kelompok, label, bobot). Tidak memuat alamat atau isi halaman.
            $table->json('signals')->nullable()->after('indicators');
            // Penjelasan AI yang sudah dibuat, disimpan supaya kuota Gemini tidak terpakai berulang.
            $table->json('explanation')->nullable()->after('checks');
        });
    }

    public function down(): void
    {
        Schema::table('jeda_link_checks', function (Blueprint $table) {
            $table->dropUnique(['token']);
        });
        Schema::table('jeda_link_checks', function (Blueprint $table) {
            $table->dropColumn(['token', 'signals', 'explanation']);
        });
    }
};
