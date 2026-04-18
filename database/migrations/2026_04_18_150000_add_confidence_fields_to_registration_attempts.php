<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('confidence_score')->nullable()->after('city');
            $table->jsonb('score_breakdown')->nullable()->after('confidence_score');
        });
    }

    public function down(): void
    {
        Schema::table('registration_attempts', function (Blueprint $table) {
            $table->dropColumn(['confidence_score', 'score_breakdown']);
        });
    }
};
