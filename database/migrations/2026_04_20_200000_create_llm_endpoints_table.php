<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_endpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('workspace_id');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();

            // Provider: ollama | anthropic | openai | platform
            $table->string('provider', 32);
            $table->string('base_url', 255);
            $table->string('model', 255);

            // API-Key Laravel-encrypted via Crypt::encryptString in Model.
            // Nullable weil lokales Ollama ohne Auth möglich ist.
            $table->text('api_key_encrypted')->nullable();

            // is_default = Fallback wenn kein agent_scope matched.
            // Max ein Default pro Workspace (App-level enforcement).
            $table->boolean('is_default')->default(false);

            // agent_scope NULL = greift für alle Agenten.
            // Sonst z.B. "chat-agent", "mayring_agent".
            $table->string('agent_scope', 64)->nullable();

            // Freie Provider-spezifische Config (z.B. Ollama options).
            $table->json('extra')->nullable();

            $table->timestamps();

            $table->index(['workspace_id', 'is_default']);
            $table->index(['workspace_id', 'agent_scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_endpoints');
    }
};
