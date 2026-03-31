<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Projekt-FK on phase tables
        DB::statement('CREATE INDEX idx_phasen_projekt ON phasen(projekt_id)');
        DB::statement('CREATE INDEX idx_p1_komp_projekt ON p1_komponenten(projekt_id)');

        // Treffer indices
        DB::statement('CREATE INDEX idx_p5_treffer_projekt ON p5_treffer(projekt_id)');
        DB::statement('CREATE INDEX idx_p5_treffer_record ON p5_treffer(record_id)');
        DB::statement('CREATE INDEX idx_p5_treffer_duplikat ON p5_treffer(ist_duplikat)');

        // FK lookup indices
        DB::statement('CREATE INDEX idx_p5_screening_treffer ON p5_screening_entscheidungen(treffer_id)');
        DB::statement('CREATE INDEX idx_p6_bewertung_treffer ON p6_qualitaetsbewertung(treffer_id)');
        DB::statement('CREATE INDEX idx_p7_extraktion_treffer ON p7_datenextraktion(treffer_id)');

        // Full-text search (German) on abstracts and titles
        DB::statement("CREATE INDEX idx_p5_treffer_abstract_fts ON p5_treffer USING gin(to_tsvector('german', coalesce(abstract, '')))");
        DB::statement("CREATE INDEX idx_p5_treffer_titel_fts ON p5_treffer USING gin(to_tsvector('german', coalesce(titel, '')))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_p5_treffer_titel_fts');
        DB::statement('DROP INDEX IF EXISTS idx_p5_treffer_abstract_fts');
        DB::statement('DROP INDEX IF EXISTS idx_p7_extraktion_treffer');
        DB::statement('DROP INDEX IF EXISTS idx_p6_bewertung_treffer');
        DB::statement('DROP INDEX IF EXISTS idx_p5_screening_treffer');
        DB::statement('DROP INDEX IF EXISTS idx_p5_treffer_duplikat');
        DB::statement('DROP INDEX IF EXISTS idx_p5_treffer_record');
        DB::statement('DROP INDEX IF EXISTS idx_p5_treffer_projekt');
        DB::statement('DROP INDEX IF EXISTS idx_p1_komp_projekt');
        DB::statement('DROP INDEX IF EXISTS idx_phasen_projekt');
    }
};
