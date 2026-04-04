<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add nullable UUID column alongside old PK
        Schema::table('contacts', function (Blueprint $table) {
            $table->uuid('uuid_id')->nullable();
        });

        // 2. Backfill all existing rows
        DB::statement("UPDATE contacts SET uuid_id = gen_random_uuid()");

        // 3. Drop old auto-increment PK
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        // 4. Rename, make non-nullable, and set as primary key
        Schema::table('contacts', function (Blueprint $table) {
            $table->renameColumn('uuid_id', 'id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->uuid('id')->nullable(false)->primary()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->id()->first();
        });
    }
};
