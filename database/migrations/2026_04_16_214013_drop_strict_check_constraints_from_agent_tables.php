<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE p2_cluster DROP CONSTRAINT IF EXISTS p2_cluster_relevanz_check');
        DB::statement('ALTER TABLE p3_disziplinen DROP CONSTRAINT IF EXISTS p3_disziplinen_art_check');
        DB::statement('ALTER TABLE p8_update_plan DROP CONSTRAINT IF EXISTS chk_update_typ');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE p2_cluster ADD CONSTRAINT p2_cluster_relevanz_check CHECK (relevanz = ANY (ARRAY['hoch'::text, 'mittel'::text, 'gering'::text]))");
        DB::statement("ALTER TABLE p3_disziplinen ADD CONSTRAINT p3_disziplinen_art_check CHECK (art = ANY (ARRAY['kerndisziplin'::text, 'angrenzend'::text]))");
        DB::statement("ALTER TABLE p8_update_plan ADD CONSTRAINT chk_update_typ CHECK (update_typ = ANY (ARRAY['living_review'::text, 'periodisch'::text]))");
    }
};
