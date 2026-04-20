<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Provider type: 'platform' (default, platform pays), 'anthropic-byo' (user's own Anthropic key),
            // 'openai-compatible' (user's own endpoint, e.g. Ollama/OpenRouter).
            $table->string('llm_provider_type')->default('platform')->after('preferred_chat_model');
            $table->string('llm_endpoint')->nullable()->after('llm_provider_type');
            // API keys encrypted at rest via Laravel 'encrypted' cast on the model.
            $table->text('llm_api_key')->nullable()->after('llm_endpoint');
            // Freier Model-Name wenn Provider kein Anthropic ist (z.B. 'qwen2.5:7b').
            $table->string('llm_custom_model')->nullable()->after('llm_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['llm_provider_type', 'llm_endpoint', 'llm_api_key', 'llm_custom_model']);
        });
    }
};
