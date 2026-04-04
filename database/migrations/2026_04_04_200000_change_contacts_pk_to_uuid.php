<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop auto-increment PK and replace with UUID
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary()->first();
        });

        // Backfill existing rows
        DB::statement("UPDATE contacts SET id = gen_random_uuid() WHERE id IS NULL");
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
