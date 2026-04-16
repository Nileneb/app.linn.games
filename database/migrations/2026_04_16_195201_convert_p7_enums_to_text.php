<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE p7_grade_einschaetzung ALTER COLUMN grade_urteil TYPE text');
        DB::statement('ALTER TABLE p7_grade_einschaetzung ALTER COLUMN rob_gesamt TYPE text');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE p7_grade_einschaetzung ALTER COLUMN grade_urteil TYPE grade_urteil USING grade_urteil::grade_urteil");
        DB::statement("ALTER TABLE p7_grade_einschaetzung ALTER COLUMN rob_gesamt TYPE rob_urteil USING rob_gesamt::rob_urteil");
    }
};
