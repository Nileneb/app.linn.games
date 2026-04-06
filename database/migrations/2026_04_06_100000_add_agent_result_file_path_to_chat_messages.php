<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Track file path for agent results stored as Markdown
            $table->string('agent_result_file_path')->nullable()->after('content');
            $table->index('agent_result_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['agent_result_file_path']);
            $table->dropColumn('agent_result_file_path');
        });
    }
};
