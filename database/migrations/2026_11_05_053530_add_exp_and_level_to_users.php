<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jeda_quiz_attempts', function (Blueprint $table) {
            $table->unsignedInteger('xp_earned')->default(0)->after('correct_count');
        });

        // Isi EXP untuk percobaan lama dengan rumus awal sistem EXP
        // (10 + 2 per level untuk tiap jawaban benar, bonus 20 jika sempurna),
        // supaya progres pemain yang sudah ada tidak hilang.
        DB::table('jeda_quiz_attempts')->orderBy('id')->each(function ($row) {
            $perCorrect = 10 + 2 * ((int) $row->level - 1);
            $xp = (int) $row->correct_count * $perCorrect;
            if ((int) $row->total_questions > 0 && (int) $row->correct_count === (int) $row->total_questions) {
                $xp += 20;
            }
            DB::table('jeda_quiz_attempts')->where('id', $row->id)->update(['xp_earned' => $xp]);
        });
    }

    public function down(): void
    {
        Schema::table('jeda_quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('xp_earned');
        });
    }
};
