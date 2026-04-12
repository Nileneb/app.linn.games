<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // registration_country auf users (neben dem bereits vorhandenen registration_ip)
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_country_code', 2)->nullable()->after('registration_ip');
            $table->string('registration_country_name', 100)->nullable()->after('registration_country_code');
            $table->string('registration_city', 100)->nullable()->after('registration_country_name');
        });

        // Tabelle für alle blockierten Registrierungsversuche
        Schema::create('registration_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ip', 45);
            $table->string('user_agent', 512)->nullable();
            $table->string('reason', 30); // 'honeypot' | 'rate_limit' | 'validation'
            $table->string('email', 255)->nullable(); // falls bekannt (bei rate_limit)
            $table->string('country_code', 2)->nullable();
            $table->string('country_name', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('ip');
            $table->index('country_code');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_attempts');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['registration_country_code', 'registration_country_name', 'registration_city']);
        });
    }
};
