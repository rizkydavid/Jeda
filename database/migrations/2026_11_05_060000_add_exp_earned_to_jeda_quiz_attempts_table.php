<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('jeda_quiz_attempts', 'exp_earned')) {
            Schema::table('jeda_quiz_attempts', function (Blueprint $table) {
                $table->unsignedInteger('exp_earned')->default(0)->after('correct_count');
            });
        }

        // Isi EXP untuk percobaan lama (yang masih 0) dengan rumus awal:
        // 6 + 2 per level untuk tiap jawaban benar, bonus 10 jika sempurna.
        DB::table('jeda_quiz_attempts')->where('exp_earned', 0)->orderBy('id')->each(function ($row) {
            $exp = (int) $row->correct_count * (6 + 2 * ((int) $row->level - 1));
            if ((int) $row->total_questions > 0 && (int) $row->correct_count === (int) $row->total_questions) {
                $exp += 10;
            }
            if ($exp > 0) {
                DB::table('jeda_quiz_attempts')->where('id', $row->id)->update(['exp_earned' => $exp]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('jeda_quiz_attempts', 'exp_earned')) {
            Schema::table('jeda_quiz_attempts', function (Blueprint $table) {
                $table->dropColumn('exp_earned');
            });
        }
    }
};
