<?php

use Database\Migrations\Support\PgsqlEnumHelpers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    use PgsqlEnumHelpers;

    // CREATE EXTENSION ist in manchen PostgreSQL-Konfigurationen nicht transaktionssicher
    // (z. B. bei shared-preload-libraries oder superuser-Anforderungen). Idempotenz
    // wird durch IF NOT EXISTS und enumExists() sichergestellt.
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');

        $enums = [
            'phase_status' => "'offen', 'in_bearbeitung', 'abgeschlossen'",
            'review_typ' => "'systematic_review', 'scoping_review', 'evidence_map'",
            'strukturmodell' => "'PICO', 'SPIDER', 'PICOS'",
            'kriterium_typ' => "'einschluss', 'ausschluss'",
            'screening_level' => "'L1_titel_abstract', 'L2_volltext'",
            'screening_entscheidung' => "'eingeschlossen', 'ausgeschlossen', 'unklar'",
            'rob_tool' => "'RoB2', 'ROBINS-I', 'CASP_qualitativ', 'AMSTAR2', 'ROBINS-I_erweitert', 'narrativ'",
            'rob_urteil' => "'niedrig', 'moderat', 'hoch', 'kritisch', 'nicht_bewertet'",
            'synthese_methode' => "'meta_analyse', 'narrative_synthese', 'thematische_synthese', 'framework_synthesis'",
            'grade_urteil' => "'stark', 'moderat', 'schwach', 'sehr_schwach'",
            'studientyp' => "'RCT', 'nicht_randomisiert', 'qualitativ', 'systematic_review', 'guideline_framework', 'konzeptuell'",
            'tool_empfehlung' => "'Rayyan', 'Covidence', 'EPPI_Reviewer', 'DistillerSR', 'ASReview', 'SWIFT_ActiveScreener'",
        ];

        foreach ($enums as $name => $values) {
            if (! self::enumExists($name)) {
                DB::statement("CREATE TYPE {$name} AS ENUM ({$values})");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $types = [
            'tool_empfehlung', 'studientyp', 'grade_urteil', 'synthese_methode',
            'rob_urteil', 'rob_tool', 'screening_entscheidung', 'screening_level',
            'kriterium_typ', 'strukturmodell', 'review_typ', 'phase_status',
        ];

        foreach ($types as $type) {
            DB::statement("DROP TYPE IF EXISTS {$type} CASCADE");
        }
    }
};
