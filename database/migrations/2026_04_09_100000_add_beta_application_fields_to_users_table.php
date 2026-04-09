<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: 'waitlisted' zum nativen Enum user_status hinzufügen
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TYPE user_status ADD VALUE IF NOT EXISTS 'waitlisted'");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->text('forschungsfrage')->nullable()->after('status');
            $table->string('forschungsbereich', 255)->nullable()->after('forschungsfrage');
            $table->string('erfahrung', 100)->nullable()->after('forschungsbereich');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['forschungsfrage', 'forschungsbereich', 'erfahrung']);
        });

        // Hinweis: PostgreSQL unterstützt kein direktes Entfernen von Enum-Werten.
        // 'waitlisted' bleibt im Typ erhalten, schadet aber nicht.
    }
};
