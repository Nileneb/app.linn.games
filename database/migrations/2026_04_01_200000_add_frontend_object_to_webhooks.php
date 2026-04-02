<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Bewusst ohne DB::transaction(): CREATE TYPE ist PostgreSQL-DDL (auto-commit).
    // Idempotenz durch PL/pgSQL EXCEPTION WHEN duplicate_object.
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            DO \$body\$ BEGIN
                CREATE TYPE webhook_frontend_object AS ENUM ('dashboard_chat');
            EXCEPTION WHEN duplicate_object THEN NULL;
            END \$body\$
        ");

        if (! Schema::hasColumn('webhooks', 'frontend_object')) {
            DB::statement('ALTER TABLE webhooks ADD COLUMN frontend_object webhook_frontend_object NULL');
            DB::statement('ALTER TABLE webhooks ADD CONSTRAINT webhooks_user_frontend_object_unique UNIQUE (user_id, frontend_object)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE webhooks DROP CONSTRAINT IF EXISTS webhooks_user_frontend_object_unique");
        DB::statement("ALTER TABLE webhooks DROP COLUMN IF EXISTS frontend_object");
        DB::statement("DROP TYPE IF EXISTS webhook_frontend_object CASCADE");
    }
};
