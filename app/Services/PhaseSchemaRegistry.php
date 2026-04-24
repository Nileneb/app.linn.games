<?php

namespace App\Services;

class PhaseSchemaRegistry
{
    /**
     * Canonical phase→table→columns map. Single source of truth for schema injection and table allowlist.
     *
     * @return array<int, array<string, string>>
     */
    public static function phaseSchemas(): array
    {
        return [
            1 => [
                'p1_komponenten' => 'projekt_id (uuid, required), komponente_kuerzel (text, required), komponente_label (text, required), modell (enum: PICO|SPIDER|PICOS, required), inhaltlicher_begriff_de, englische_entsprechung, mesh_term, thesaurus_term, anmerkungen, synonyme (jsonb)',
                'p1_kriterien' => 'projekt_id (uuid, required), beschreibung (text, required), kriterium_typ (enum: einschluss|ausschluss, required), begruendung, quellbezug',
                'p1_strukturmodell_wahl' => 'projekt_id (uuid, required), modell (enum: PICO|SPIDER|PICOS, required), gewaehlt (boolean, required), begruendung, quellbezug',
                'p1_warnsignale' => 'projekt_id (uuid, required), lfd_nr (integer, required), warnsignal (text, required), moegliche_auswirkung, handlungsempfehlung',
            ],
            2 => [
                'p2_cluster' => 'projekt_id (uuid, required), cluster_id (text, required), cluster_label (text, required), beschreibung, treffer_schaetzung (integer), relevanz (enum: hoch|mittel|gering — oder NULL lassen)',
                'p2_review_typ_entscheidung' => 'projekt_id (uuid, required), review_typ (enum: systematic_review|scoping_review|rapid_review|meta_analysis, required), passt (boolean), begruendung',
                'p2_mapping_suchstring_komponenten' => 'projekt_id (uuid, required), komponente_label (text, required), sprache, trunkierung_genutzt (boolean, required), suchbegriffe (jsonb), anmerkung',
                'p2_trefferlisten' => 'projekt_id (uuid, required), datenbank (text, required), suchstring, treffer_gesamt (integer), einschaetzung, anpassung_notwendig (boolean, required), suchdatum (date)',
            ],
            3 => [
                'p3_datenbankmatrix' => 'projekt_id (uuid, required), datenbank (text, required), disziplin, abdeckung, besonderheit (NICHT "besondere"!), zugang (enum: frei|kostenpflichtig|institutionell — oder NULL), empfohlen (boolean), begruendung',
                'p3_disziplinen' => 'projekt_id (uuid, required), disziplin (text, required), art (enum: kerndisziplin|angrenzend — NUR diese zwei Werte oder NULL!), relevanz (enum: hoch|mittel|gering — oder NULL), anmerkung',
                'p3_geografische_filter' => 'projekt_id (uuid, required), region_land (text, required), validierter_filter_vorhanden (boolean, required), filtername_quelle, sensitivitaet_prozent, hilfsstrategie',
                'p3_graue_literatur' => 'projekt_id (uuid, required), quelle (text, required), typ, url, suchpfad, relevanz, anmerkung',
            ],
            4 => [
                'p4_suchstrings' => 'projekt_id (uuid, required), datenbank (text, required), suchstring (text, required), version (text, required), feldeinschraenkung, gesetzte_filter (jsonb), treffer_anzahl (integer), einschaetzung, aenderungs_grund, suchdatum (date)',
                'p4_thesaurus_mapping' => 'projekt_id (uuid, required), freitext_de, freitext_en, mesh_term, emtree_term, psycinfo_term, anmerkung',
                'p4_anpassungsprotokoll' => 'suchstring_id (uuid, required — FK zu p4_suchstrings.id), version (text, required), datum (date), aenderung, grund, treffer_vorher (integer), treffer_nachher (integer), entscheidung',
            ],
            5 => [
                'p5_treffer' => 'projekt_id (uuid, required), record_id (text, required), titel, autoren, jahr (integer), journal, doi, abstract, datenbank_quelle, ist_duplikat (boolean, required)',
                'p5_screening_kriterien' => 'projekt_id (uuid, required), beschreibung (text, required), level (enum: L1_titel_abstract|L2_volltext, required), kriterium_typ (enum: einschluss|ausschluss, required), beispiel',
                'p5_screening_entscheidungen' => 'treffer_id (uuid, required — FK zu p5_treffer.id), level (enum: L1_titel_abstract|L2_volltext, required), entscheidung (enum: eingeschlossen|ausgeschlossen|unklar, required), ausschlussgrund, reviewer, datum (date), anmerkung',
                'p5_prisma_zahlen' => 'projekt_id (uuid, required), identifiziert_gesamt (integer), davon_datenbank_treffer (integer), davon_graue_literatur (integer), nach_deduplizierung (integer), ausgeschlossen_l1 (integer), volltext_geprueft (integer), ausgeschlossen_l2 (integer), eingeschlossen_final (integer)',
                'p5_tool_entscheidung' => 'projekt_id (uuid, required), tool (enum: Rayyan|Covidence|EPPI_Reviewer|DistillerSR|ASReview|SWIFT_ActiveScreener, required), gewaehlt (boolean), begruendung',
            ],
            6 => [
                'p6_qualitaetsbewertung' => 'treffer_id (uuid, required), studientyp (enum: RCT|cohort|case_control|cross_sectional|qualitative|systematic_review|other, required), rob_tool (enum: RoB2|ROBINS-I|CASP|NOS|GRADE|other, required), gesamturteil (enum: niedrig|moderat|hoch|kritisch, required), hauptproblem, im_review_behalten (boolean, required), anmerkung, bewertet_von, bewertet_am (date)',
                'p6_luckenanalyse' => 'projekt_id (uuid, required), fehlender_aspekt (text, required), fehlender_studientyp, moegliche_konsequenz, empfehlung',
            ],
            7 => [
                'p7_datenextraktion' => 'treffer_id (uuid, required), land, stichprobe_kontext, phaenomen_intervention, outcome_ergebnis, hauptbefund, anmerkung, qualitaetsurteil',
                'p7_muster_konsistenz' => 'projekt_id (uuid, required), muster_befund (text, required), moegliche_erklaerung, unterstuetzende_quellen (jsonb), widersprechende_quellen (jsonb)',
                'p7_synthese_methode' => 'projekt_id (uuid, required), methode (enum: meta_analyse|narrative_synthese|thematische_synthese|framework_synthese|other, required), gewaehlt (boolean, required), begruendung',
                'p7_grade_einschaetzung' => 'projekt_id (uuid, required), outcome (text, required), grade_urteil (enum: hoch|moderat|niedrig|sehr_niedrig, required), studienanzahl, inkonsistenz, indirektheit, impraezision, begruendung',
            ],
            8 => [
                'p8_suchprotokoll' => 'projekt_id (uuid, required), datenbank (text, required), suchstring_final (text, required), suchdatum (date), db_version, gesetzte_filter (jsonb), treffer_gesamt (integer), treffer_eindeutig (integer)',
                'p8_limitationen' => 'projekt_id (uuid, required), limitationstyp (text, required), beschreibung, auswirkung_auf_vollstaendigkeit',
                'p8_reproduzierbarkeitspruefung' => 'projekt_id (uuid, required), pruefpunkt (text, required), erfuellt (boolean), anmerkung',
                'p8_update_plan' => 'projekt_id (uuid, required), update_typ, intervall, verantwortlich, tool, naechstes_update (date)',
            ],
        ];
    }

    /**
     * Returns the list of writable table names for a given phase.
     *
     * @return string[]
     */
    public static function tablesForPhase(int $phaseNr): array
    {
        return array_keys(static::phaseSchemas()[$phaseNr] ?? []);
    }

    /**
     * Gibt das exakte DB-Schema der für diese Phase beschreibbaren Tabellen zurück.
     */
    public static function schemaForPhase(int $phaseNr): string
    {
        $tables = static::phaseSchemas()[$phaseNr] ?? [];
        if (empty($tables)) {
            return '';
        }

        $lines = [];
        foreach ($tables as $table => $columns) {
            $lines[] = "**{$table}**: {$columns}";
        }

        return implode("\n", $lines);
    }

    /**
     * Bestimmt welche Relationen für die gegebene Phase geladen werden.
     *
     * @return string[]
     */
    public static function relationsForPhase(int $phaseNr): array
    {
        $relations = [];

        if ($phaseNr >= 2) {
            $relations[] = 'p1Komponenten';
            $relations[] = 'p1Kriterien';
        }
        if ($phaseNr >= 3) {
            $relations[] = 'p2Cluster';
        }
        if ($phaseNr >= 4) {
            $relations[] = 'p3Datenbankmatrix';
        }
        if ($phaseNr >= 5) {
            $relations[] = 'p4Suchstrings';
        }
        if ($phaseNr >= 6) {
            $relations[] = 'p5Treffer';
        }
        if ($phaseNr >= 7) {
            $relations[] = 'p6Qualitaetsbewertungen';
        }

        return $relations;
    }
}
