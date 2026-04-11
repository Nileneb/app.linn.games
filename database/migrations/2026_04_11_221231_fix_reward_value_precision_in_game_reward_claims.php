<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_reward_claims', function (Blueprint $table) {
            $table->decimal('reward_value', 10, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('game_reward_claims', function (Blueprint $table) {
            $table->decimal('reward_value', 8, 2)->change();
        });
    }
};
