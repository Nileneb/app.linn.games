<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Bewusst ohne DB::transaction(): CREATE TYPE + ALTER TABLE ... TYPE USING
    // sind PostgreSQL-DDL (auto-commit). ALTER TYPE ADD VALUE ist in Transaktionen
    // nicht erlaubt. Idempotenz durch PL/pgSQL EXCEPTION WHEN duplicate_object.
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Idempotent: ignore if type already exists after migrate:fresh
            DB::statement("
                DO \$body\$ BEGIN
                    CREATE TYPE user_status AS ENUM ('trial', 'active', 'suspended', 'cancelled');
                EXCEPTION WHEN duplicate_object THEN NULL;
                END \$body\$
            ");

            if (! Schema::hasColumn('users', 'status')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('status')->default('trial')->after('email');
                });

                DB::statement('ALTER TABLE users ALTER COLUMN status DROP DEFAULT');
                DB::statement('ALTER TABLE users ALTER COLUMN status TYPE user_status USING status::user_status');
                DB::statement("ALTER TABLE users ALTER COLUMN status SET DEFAULT 'trial'::user_status");
            }
        } else {
            // SQLite fallback for tests
            if (! Schema::hasColumn('users', 'status')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('status')->default('trial')->after('email');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP TYPE IF EXISTS user_status CASCADE");
        }
    }
};
