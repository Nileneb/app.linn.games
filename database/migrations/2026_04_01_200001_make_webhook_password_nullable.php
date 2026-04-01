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

        // password hat für ausgehende Webhooks keine Bedeutung → nullable
        DB::statement('ALTER TABLE webhooks ALTER COLUMN password DROP NOT NULL');
        DB::statement("ALTER TABLE webhooks ALTER COLUMN password SET DEFAULT ''");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("UPDATE webhooks SET password = '' WHERE password IS NULL");
        DB::statement('ALTER TABLE webhooks ALTER COLUMN password SET NOT NULL');
        DB::statement('ALTER TABLE webhooks ALTER COLUMN password DROP DEFAULT');
    }
};
