<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // studientyp ENUM: add values the P6 agent actually returns
        // (ClaudeContextBuilder tells agent to use these)
        $studientypValues = ['cohort', 'case_control', 'cross_sectional', 'qualitative', 'other'];
        foreach ($studientypValues as $value) {
            DB::statement("ALTER TYPE studientyp ADD VALUE IF NOT EXISTS '{$value}'");
        }

        // rob_tool ENUM: add values the P6 agent actually returns
        $robToolValues = ['CASP', 'NOS', 'GRADE', 'other'];
        foreach ($robToolValues as $value) {
            DB::statement("ALTER TYPE rob_tool ADD VALUE IF NOT EXISTS '{$value}'");
        }
    }

    public function down(): void
    {
        // PostgreSQL does not support removing enum values — no-op
    }
};
