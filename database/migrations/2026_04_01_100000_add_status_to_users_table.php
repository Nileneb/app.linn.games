<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE TYPE user_status AS ENUM ('trial', 'active', 'suspended', 'cancelled')");

            Schema::table('users', function (Blueprint $table) {
                $table->string('status')->default('trial')->after('email');
            });

            DB::statement("ALTER TABLE users ALTER COLUMN status TYPE user_status USING status::user_status");
        } else {
            // SQLite fallback for tests
            Schema::table('users', function (Blueprint $table) {
                $table->string('status')->default('trial')->after('email');
            });
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
