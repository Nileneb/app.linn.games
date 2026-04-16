<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE p3_datenbankmatrix DROP CONSTRAINT IF EXISTS p3_datenbankmatrix_zugang_check');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE p3_datenbankmatrix ADD CONSTRAINT p3_datenbankmatrix_zugang_check CHECK (zugang = ANY (ARRAY['frei'::text, 'kostenpflichtig'::text, 'institutionell'::text]))");
    }
};
