<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jeda_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 100)->index();
            $table->unsignedTinyInteger('level');
            $table->date('quiz_date');
            $table->unsignedTinyInteger('score')->default(0);
            $table->unsignedTinyInteger('total_questions')->default(10);
            $table->unsignedTinyInteger('correct_count')->default(0);
            $table->json('answers')->nullable();
            $table->timestamps();
            $table->index(['session_key', 'level', 'quiz_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jeda_quiz_attempts');
    }
};
