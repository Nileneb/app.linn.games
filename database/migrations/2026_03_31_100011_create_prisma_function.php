<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Erstellt die PostgreSQL-Funktion berechne_prisma_zahlen(UUID).
     *
     * Diese serverseitige SQL-Funktion berechnet die PRISMA-2020-Kennzahlen
     * für ein Recherche-Projekt direkt auf DB-Ebene. Sie zählt aus p5_treffer
     * und p5_screening_entscheidungen die Werte für das PRISMA-Flussdiagramm:
     * Identifiziert → Duplikate → L1-Ausschluss → Volltext → L2-Ausschluss → Final.
     *
     * Aufruf: SELECT * FROM berechne_prisma_zahlen('projekt-uuid');
     *
     * Die SELECT-Logik erscheint auf den ersten Blick komplex, ist aber fachlich notwendig:
     * Jeder FILTER-Zweig implementiert genau eine PRISMA-2020-Stufe (Identifizierung → Duplikate
     * → L1-Ausschluss → Volltext → L2-Ausschluss → Final). Die Korrelation über EXISTS
     * ist erforderlich, weil Treffer mehrfach in verschiedenen Screening-Ebenen vorkommen können.
     * Eine Vereinfachung auf JOIN würde die Semantik verändern (Doppelzählung bei mehreren
     * Screening-Einträgen pro Treffer). CREATE OR REPLACE stellt Idempotenz sicher.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION berechne_prisma_zahlen(p_projekt_id UUID)
            RETURNS TABLE (
                identifiziert_gesamt    BIGINT,
                duplikate               BIGINT,
                nach_deduplizierung     BIGINT,
                ausgeschlossen_l1       BIGINT,
                volltext_geprueft       BIGINT,
                ausgeschlossen_l2       BIGINT,
                eingeschlossen_final    BIGINT
            ) LANGUAGE sql AS \$\$
                SELECT
                    COUNT(*)                                                    AS identifiziert_gesamt,
                    COUNT(*) FILTER (WHERE ist_duplikat)                       AS duplikate,
                    COUNT(*) FILTER (WHERE NOT ist_duplikat)                   AS nach_deduplizierung,

                    COUNT(*) FILTER (
                        WHERE NOT ist_duplikat
                        AND EXISTS (
                            SELECT 1 FROM p5_screening_entscheidungen se
                            WHERE se.treffer_id = t.id
                              AND se.level = 'L1_titel_abstract'
                              AND se.entscheidung = 'ausgeschlossen'
                        )
                    )                                                           AS ausgeschlossen_l1,

                    COUNT(*) FILTER (
                        WHERE NOT ist_duplikat
                        AND EXISTS (
                            SELECT 1 FROM p5_screening_entscheidungen se
                            WHERE se.treffer_id = t.id
                              AND se.level = 'L2_volltext'
                        )
                    )                                                           AS volltext_geprueft,

                    COUNT(*) FILTER (
                        WHERE NOT ist_duplikat
                        AND EXISTS (
                            SELECT 1 FROM p5_screening_entscheidungen se
                            WHERE se.treffer_id = t.id
                              AND se.level = 'L2_volltext'
                              AND se.entscheidung = 'ausgeschlossen'
                        )
                    )                                                           AS ausgeschlossen_l2,

                    COUNT(*) FILTER (
                        WHERE NOT ist_duplikat
                        AND EXISTS (
                            SELECT 1 FROM p5_screening_entscheidungen se
                            WHERE se.treffer_id = t.id
                              AND se.level = 'L2_volltext'
                              AND se.entscheidung = 'eingeschlossen'
                        )
                    )                                                           AS eingeschlossen_final

                FROM p5_treffer t
                WHERE t.projekt_id = p_projekt_id;
            \$\$
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP FUNCTION IF EXISTS berechne_prisma_zahlen(UUID)');
    }
};
