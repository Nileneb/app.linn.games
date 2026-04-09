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

        if (Schema::hasColumn('p4_suchstrings', 'anpassung')) {
            DB::statement('ALTER TABLE p4_suchstrings RENAME COLUMN anpassung TO aenderungs_grund');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasColumn('p4_suchstrings', 'aenderungs_grund')) {
            DB::statement('ALTER TABLE p4_suchstrings RENAME COLUMN aenderungs_grund TO anpassung');
        }
    }
};
