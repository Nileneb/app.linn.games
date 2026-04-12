<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The status column uses a CHECK constraint (Laravel enum on Postgres).
        // Extend it to include 'deferred' for automatic daily-limit rescheduling.
        DB::statement('ALTER TABLE phase_agent_results DROP CONSTRAINT IF EXISTS phase_agent_results_status_check');
        DB::statement("ALTER TABLE phase_agent_results ADD CONSTRAINT phase_agent_results_status_check CHECK (status IN ('pending', 'completed', 'failed', 'deferred'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE phase_agent_results DROP CONSTRAINT IF EXISTS phase_agent_results_status_check');
        DB::statement("ALTER TABLE phase_agent_results ADD CONSTRAINT phase_agent_results_status_check CHECK (status IN ('pending', 'completed', 'failed'))");
    }
};
