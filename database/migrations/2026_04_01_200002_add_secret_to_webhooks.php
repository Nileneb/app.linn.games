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

        // secret wird als Query-Parameter (?secret=...) an die Webhook-URL angehängt
        DB::statement('ALTER TABLE webhooks ADD COLUMN IF NOT EXISTS secret TEXT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE webhooks DROP COLUMN IF EXISTS secret');
    }
};
