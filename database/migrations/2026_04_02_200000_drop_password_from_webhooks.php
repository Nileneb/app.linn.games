<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // password wird durch secret ersetzt (encoded in URL als Query-Parameter)
        Schema::table('webhooks', function ($table) {
            $table->dropColumn('password');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Rollback: password-Spalte wiederherstellen (nullable)
        Schema::table('webhooks', function ($table) {
            $table->text('password')->nullable();
        });
    }
};
