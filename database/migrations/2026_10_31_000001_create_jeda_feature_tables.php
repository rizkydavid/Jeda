<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jeda_message_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 100)->index();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('risk_level', 30);
            $table->json('indicators')->nullable();
            $table->unsignedInteger('text_length')->default(0);
            // Isi pesan tidak disimpan demi privasi.
            $table->timestamps();
        });

        Schema::create('jeda_link_checks', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 100)->index();
            $table->string('hostname', 253)->nullable();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('risk_level', 30);
            $table->json('indicators')->nullable();
            // URL lengkap tidak disimpan.
            $table->timestamps();
        });

        Schema::create('jeda_file_checks', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 100)->index();
            $table->string('extension', 20)->nullable();
            $table->boolean('double_extension')->default(false);
            $table->boolean('executable_type')->default(false);
            $table->json('indicators')->nullable();
            // Nama file asli tidak disimpan.
            $table->timestamps();
        });

        Schema::create('jeda_simulation_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 100)->index();
            $table->string('scenario', 80)->default('rekruter-1');
            $table->unsignedTinyInteger('selected_option');
            $table->boolean('strong_choice')->default(false);
            $table->unsignedTinyInteger('score')->default(0);
            $table->timestamps();
        });

        Schema::create('jeda_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 100);
            $table->string('feature', 50);
            $table->string('situation', 80)->default('umum');
            $table->string('item_key', 100);
            $table->boolean('is_done')->default(false);
            $table->timestamps();
            $table->unique(['session_key', 'feature', 'situation', 'item_key'], 'jeda_checklist_unique');
            $table->index(['session_key', 'feature']);
        });

        Schema::create('jeda_support_actions', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 100)->index();
            $table->string('feature', 50);
            $table->string('action', 80);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jeda_support_actions');
        Schema::dropIfExists('jeda_checklist_items');
        Schema::dropIfExists('jeda_simulation_attempts');
        Schema::dropIfExists('jeda_file_checks');
        Schema::dropIfExists('jeda_link_checks');
        Schema::dropIfExists('jeda_message_analyses');
    }
};
