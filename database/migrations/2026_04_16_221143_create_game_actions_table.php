<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('session_id')->nullable()->index();
            $table->uuid('projekt_id')->nullable()->index();
            $table->string('action', 50);
            $table->string('enemy_type', 50)->nullable();
            $table->string('cluster_id', 100)->nullable();
            $table->uuid('paper_id')->nullable();
            $table->integer('reaction_ms')->nullable();
            $table->integer('wave')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_actions');
    }
};
