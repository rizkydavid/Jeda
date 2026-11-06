<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jeda_link_checks', function (Blueprint $table) {
            $table->string('final_hostname', 253)->nullable()->after('hostname');
            $table->unsignedInteger('domain_age_days')->nullable()->after('indicators');
            // lengkap | sebagian | terbatas. Dipisah dari skor risiko.
            $table->string('coverage', 20)->nullable()->after('domain_age_days');
            // Ringkasan status tiap pemeriksaan (state dan hasil saja, tanpa URL).
            $table->json('checks')->nullable()->after('coverage');
        });
    }

    public function down(): void
    {
        Schema::table('jeda_link_checks', function (Blueprint $table) {
            $table->dropColumn(['final_hostname', 'domain_age_days', 'coverage', 'checks']);
        });
    }
};
