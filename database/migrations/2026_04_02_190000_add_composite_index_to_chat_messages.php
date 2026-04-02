<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->index(
                ['workspace_id', 'user_id', 'created_at'],
                'idx_chat_msg_ws_user_created'
            );
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('idx_chat_msg_ws_user_created');
        });
    }
};
