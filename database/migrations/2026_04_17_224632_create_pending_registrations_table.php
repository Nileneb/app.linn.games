<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('forschungsfrage');
            $table->string('forschungsbereich');
            $table->string('erfahrung');
            $table->uuid('token')->unique();
            $table->timestamp('token_expires_at');
            $table->integer('confidence_score')->default(0);
            $table->json('score_breakdown')->default('{"timing":0,"timezone":0,"tor":0,"disposable":0}');
            $table->string('registration_ip')->nullable();
            $table->string('registration_country_code', 2)->nullable();
            $table->string('registration_country_name')->nullable();
            $table->string('registration_city')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('needs_review')->default(false);
            $table->enum('status', ['pending_email', 'verified', 'rejected'])->default('pending_email');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
