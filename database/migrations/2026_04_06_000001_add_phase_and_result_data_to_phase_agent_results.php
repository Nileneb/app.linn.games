<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phase_agent_results', function (Blueprint $table) {
            // Add phase column if it doesn't exist
            if (!Schema::hasColumn('phase_agent_results', 'phase')) {
                $table->string('phase')->nullable()->after('agent_config_key')->comment('Phase name: recherche, screening, auswertung');
            }

            // Add result_data JSON column if it doesn't exist
            if (!Schema::hasColumn('phase_agent_results', 'result_data')) {
                $table->json('result_data')->nullable()->after('content')->comment('Structured result data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('phase_agent_results', function (Blueprint $table) {
            if (Schema::hasColumn('phase_agent_results', 'phase')) {
                $table->dropColumn('phase');
            }
            if (Schema::hasColumn('phase_agent_results', 'result_data')) {
                $table->dropColumn('result_data');
            }
        });
    }
};
