<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jeda_file_checks', function (Blueprint $table) {
            // Nama file dan SHA-256 TIDAK disimpan. Yang disimpan hanya ringkasan hasil.
            $table->string('detected_type', 20)->nullable()->after('extension');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('detected_type');
            $table->unsignedTinyInteger('risk_score')->default(0)->after('executable_type');
            $table->string('risk_level', 30)->nullable()->after('risk_score');
            // lengkap | sebagian | terbatas. Dipisah dari skor risiko.
            $table->string('coverage', 20)->nullable()->after('indicators');
            $table->json('signals')->nullable()->after('coverage');
            $table->json('checks')->nullable()->after('signals');
        });
    }

    public function down(): void
    {
        Schema::table('jeda_file_checks', function (Blueprint $table) {
            $table->dropColumn(['detected_type', 'size_bytes', 'risk_score', 'risk_level', 'coverage', 'signals', 'checks']);
        });
    }
};
