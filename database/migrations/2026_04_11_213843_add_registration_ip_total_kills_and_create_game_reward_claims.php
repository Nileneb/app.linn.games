<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_ip', 45)->nullable()->after('status');
            $table->unsignedInteger('total_kills')->default(0)->after('registration_ip');
        });

        Schema::create('game_reward_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('kills_threshold');
            $table->string('reward_type', 20);   // 'topup' | 'discount'
            $table->decimal('reward_value', 8, 2);
            $table->timestamp('claimed_at');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'kills_threshold']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_reward_claims');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['registration_ip', 'total_kills']);
        });
    }
};
