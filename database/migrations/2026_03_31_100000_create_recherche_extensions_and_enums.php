<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');

        DB::statement("CREATE TYPE phase_status AS ENUM ('offen', 'in_bearbeitung', 'abgeschlossen')");
        DB::statement("CREATE TYPE review_typ AS ENUM ('systematic_review', 'scoping_review', 'evidence_map')");
        DB::statement("CREATE TYPE strukturmodell AS ENUM ('PICO', 'SPIDER', 'PICOS')");
        DB::statement("CREATE TYPE kriterium_typ AS ENUM ('einschluss', 'ausschluss')");
        DB::statement("CREATE TYPE screening_level AS ENUM ('L1_titel_abstract', 'L2_volltext')");
        DB::statement("CREATE TYPE screening_entscheidung AS ENUM ('eingeschlossen', 'ausgeschlossen', 'unklar')");
        DB::statement("CREATE TYPE rob_tool AS ENUM ('RoB2', 'ROBINS-I', 'CASP_qualitativ', 'AMSTAR2', 'ROBINS-I_erweitert', 'narrativ')");
        DB::statement("CREATE TYPE rob_urteil AS ENUM ('niedrig', 'moderat', 'hoch', 'kritisch', 'nicht_bewertet')");
        DB::statement("CREATE TYPE synthese_methode AS ENUM ('meta_analyse', 'narrative_synthese', 'thematische_synthese', 'framework_synthesis')");
        DB::statement("CREATE TYPE grade_urteil AS ENUM ('stark', 'moderat', 'schwach', 'sehr_schwach')");
        DB::statement("CREATE TYPE studientyp AS ENUM ('RCT', 'nicht_randomisiert', 'qualitativ', 'systematic_review', 'guideline_framework', 'konzeptuell')");
        DB::statement("CREATE TYPE tool_empfehlung AS ENUM ('Rayyan', 'Covidence', 'EPPI_Reviewer', 'DistillerSR', 'ASReview', 'SWIFT_ActiveScreener')");
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
