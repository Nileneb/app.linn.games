<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE phase_agent_results DROP CONSTRAINT IF EXISTS phase_agent_results_status_check');
        DB::statement("ALTER TABLE phase_agent_results ADD CONSTRAINT phase_agent_results_status_check CHECK (status IN ('pending', 'completed', 'failed', 'deferred', 'out_of_credits'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE phase_agent_results DROP CONSTRAINT IF EXISTS phase_agent_results_status_check');
        DB::statement("ALTER TABLE phase_agent_results ADD CONSTRAINT phase_agent_results_status_check CHECK (status IN ('pending', 'completed', 'failed', 'deferred'))");
    }
};
