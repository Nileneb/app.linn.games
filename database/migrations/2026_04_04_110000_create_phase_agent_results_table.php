<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phase_agent_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('projekt_id')->constrained('projekte')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('phase_nr')->comment('Phase number 1-8');
            $table->string('agent_config_key')->comment('Config key: e.g., scoping_mapping_agent');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->text('content')->nullable()->comment('Agent response content (successful or error)');
            $table->text('error_message')->nullable()->comment('Detailed error message if failed');
            $table->timestamps();

            // Unique index: one result per (projekt, phase, agent_config_key) at a time
            // Multiple results per phase allowed over time (retries, manual re-runs)
            $table->index(['projekt_id', 'phase_nr', 'agent_config_key', 'created_at'], 'idx_phase_agent_latest');
            $table->index(['status', 'created_at'], 'idx_phase_agent_pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_agent_results');
    }
};
