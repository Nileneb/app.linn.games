<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('p8_suchprotokoll', function (Blueprint $table) {
            $table->uuid('projekt_id')->nullable()->after('id');
        });

        // Backfill projekt_id from p4_suchstrings
        DB::statement('
            UPDATE p8_suchprotokoll sp
            SET projekt_id = ss.projekt_id
            FROM p4_suchstrings ss
            WHERE sp.suchstring_id = ss.id
        ');

        // Remove orphaned rows that have no suchstring and thus no project
        DB::table('p8_suchprotokoll')
            ->whereNull('projekt_id')
            ->delete();

        // Make NOT NULL and add FK
        DB::statement('ALTER TABLE p8_suchprotokoll ALTER COLUMN projekt_id SET NOT NULL');

        Schema::table('p8_suchprotokoll', function (Blueprint $table) {
            $table->foreign('projekt_id', 'p8_suchprotokoll_projekt_fk')
                ->references('id')
                ->on('projekte')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('p8_suchprotokoll', function (Blueprint $table) {
            $table->dropForeign('p8_suchprotokoll_projekt_fk');
            $table->dropColumn('projekt_id');
        });
    }
};
