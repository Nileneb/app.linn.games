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

        Schema::create('webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('name');
            $table->text('slug')->unique();
            $table->text('url');
            $table->text('password');
            $table->timestampTz('created_at')->default(DB::raw('now()'));
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->uuid('webhook_id')->nullable();
            $table->foreign('webhook_id')->references('id')->on('webhooks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['webhook_id']);
            $table->dropColumn('webhook_id');
        });
        Schema::dropIfExists('webhooks');
    }
};
