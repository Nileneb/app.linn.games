<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL-only: uuid-ossp extension is provisioned in
        // 2026_03_31_100000_create_recherche_extensions_and_enums.php
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->renameColumn('id', 'legacy_id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->uuid('id')->nullable()->default(DB::raw('uuid_generate_v4()'));
        });

        DB::statement('UPDATE contacts SET id = uuid_generate_v4() WHERE id IS NULL');
        DB::statement('ALTER TABLE contacts DROP CONSTRAINT IF EXISTS contacts_pkey');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('legacy_id');
        });

        DB::statement('ALTER TABLE contacts ALTER COLUMN id SET NOT NULL');
        DB::statement('ALTER TABLE contacts ADD PRIMARY KEY (id)');
    }

    public function down(): void
    {
        // PostgreSQL-only (see up())
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE contacts DROP CONSTRAINT IF EXISTS contacts_pkey');

        Schema::table('contacts', function (Blueprint $table) {
            $table->renameColumn('id', 'legacy_uuid');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
        });

        DB::statement("CREATE SEQUENCE IF NOT EXISTS contacts_id_seq OWNED BY contacts.id");
        DB::statement("ALTER TABLE contacts ALTER COLUMN id SET DEFAULT nextval('contacts_id_seq')");
        DB::statement("UPDATE contacts SET id = nextval('contacts_id_seq') WHERE id IS NULL");
        DB::statement("
            SELECT setval(
                'contacts_id_seq',
                COALESCE((SELECT MAX(id) FROM contacts), 1),
                COALESCE((SELECT MAX(id) FROM contacts), 0) > 0
            )
        ");
        DB::statement('ALTER TABLE contacts ALTER COLUMN id SET NOT NULL');
        DB::statement('ALTER TABLE contacts ADD PRIMARY KEY (id)');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('legacy_uuid');
        });
    }
};
