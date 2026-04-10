<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 6)->unique();
            $table->unsignedBigInteger('host_user_id');
            $table->foreign('host_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('status', ['waiting', 'active', 'ended'])->default('waiting');
            $table->timestamps();
        });

        Schema::create('game_session_players', function (Blueprint $table) {
            $table->uuid('session_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('score')->default(0);
            $table->integer('kills')->default(0);
            $table->timestamp('joined_at')->useCurrent();
            $table->primary(['session_id', 'user_id']);
            $table->foreign('session_id')->references('id')->on('game_sessions')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_session_players');
        Schema::dropIfExists('game_sessions');
    }
};
