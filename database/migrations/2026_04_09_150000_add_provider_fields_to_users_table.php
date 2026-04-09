<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider', 50)->nullable()->after('remember_token');
            $table->string('provider_id', 255)->nullable()->after('provider');
            $table->unique(['provider', 'provider_id'], 'users_provider_provider_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_provider_provider_id_unique');
            $table->dropColumn(['provider', 'provider_id']);
        });
    }
};
