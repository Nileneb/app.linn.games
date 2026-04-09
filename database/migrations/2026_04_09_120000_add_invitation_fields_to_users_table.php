<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ALTER TYPE ... ADD VALUE kann in PostgreSQL nicht innerhalb einer Transaktion
    // ausgeführt werden. $withinTransaction = false deaktiviert den TX-Wrapper.
    public $withinTransaction = false;

    public function up(): void
    {
        // PostgreSQL enum-Typen können nicht innerhalb einer Transaktion erweitert werden.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TYPE user_status ADD VALUE IF NOT EXISTS 'invited'");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('invitation_token', 64)->nullable()->unique()->after('remember_token');
            $table->timestamp('invitation_expires_at')->nullable()->after('invitation_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invitation_token', 'invitation_expires_at']);
        });

        // PostgreSQL unterstützt kein DROP VALUE für Enum-Typen.
        // Der 'invited'-Wert bleibt im down()-Pfad erhalten.
    }
};
