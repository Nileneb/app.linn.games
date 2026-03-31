<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
        DB::statement('DROP FUNCTION IF EXISTS berechne_prisma_zahlen(UUID)');
    }
};
