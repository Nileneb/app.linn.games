<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20); // 'user' or 'assistant'
            $table->text('content');
            $table->timestampTz('created_at')->default(DB::raw('now()'));
        });

        DB::statement("ALTER TABLE chat_messages ADD CONSTRAINT chat_messages_role_check CHECK (role IN ('user', 'assistant'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
