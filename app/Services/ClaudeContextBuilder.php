<?php

namespace App\Services;

use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Log;

class ClaudeContextBuilder
{
    /**
     * Baut einen Markdown-Kontext-Block aus DB-Daten.
     * Dieser Block wird dem System-Prompt angehängt.
     *
     * Kein SET LOCAL, kein direkter DB-Zugriff vom Agent.
     *
     * @param  array{projekt_id?: string, workspace_id?: string, user_id?: mixed, phase_nr?: int, structured_output?: bool}  $context
     */
    public function build(array $context): string
    {
        $projektId = $context['projekt_id'] ?? null;

        if (! $projektId) {
            return '';
        }

        $projekt = Projekt::find($projektId);

        if (! $projekt) {
            Log::warning('ClaudeContextBuilder: Projekt nicht gefunden', ['projekt_id' => $projektId]);

            return '';
        }

        $phaseNr = (int) ($context['phase_nr'] ?? 0);
        $lines = [];

        $lines[] = '## Projektkontext';
        $lines[] = '';
        $lines[] = "- **Forschungsfrage:** {$projekt->forschungsfrage}";
        $lines[] = '- **Review-Typ:** '.($projekt->review_typ ?? 'nicht festgelegt');
        $lines[] = '- **Projekttitel:** '.$projekt->titel;

        if ($phaseNr > 0) {
            $lines[] = "- **Aktuelle Phase (Phase: P{$phaseNr})**";
        }

        $lines[] = '';

        // Phasendaten vorladen (kumulativ je nach Phase)
        $this->appendPhaseData($lines, $projekt, $phaseNr);

        // Output-Anforderung
        if ($context['structured_output'] ?? false) {
            $phaseNr = (int) ($context['phase_nr'] ?? 0);
            $agentKey = $context['agent_config_key'] ?? 'worker';

            $lines[] = '## Output-Anforderung';
            $lines[] = '';
            $lines[] = 'REGEL: Kein SQL. Kein SELECT. Nur neue INSERT-Daten in db_payload.tables.';
            $lines[] = '';
            // Restrict Pi agent to phase-specific tables only
            $allowedTables = $this->tablesForPhase($phaseNr);
            if (! empty($allowedTables)) {
                $lines[] = '**ERLAUBTE TABELLEN FÜR DIESE PHASE (NUR DIESE — keine anderen Tabellennamen verwenden!):**';
                foreach ($allowedTables as $tbl) {
                    $lines[] = "- `{$tbl}`";
                }
                $lines[] = '';
            }

            $lines[] = 'Gib exakt EIN gültiges JSON-Objekt zurück. Kein Text davor oder danach. Keine Markdown-Fences.';
            $lines[] = '';
            // Build concrete table example from allowed tables
            $exampleTables = '';
            if (! empty($allowedTables)) {
                $firstTable = $allowedTables[0];
                $projektIdEx = (string) ($context['projekt_id'] ?? 'PROJEKT-UUID');
                $exampleTables = '"'.$firstTable.'": [{"projekt_id": "'.$projektIdEx.'", "...weitere Felder laut Schema...": "..."}]';
            }
            $lines[] = 'Pflichtstruktur (EXAKT so — keine anderen Schlüssel!):';
            $lines[] = '```json';
            $lines[] = '{';
            $lines[] = '  "meta": {"phase": '.$phaseNr.', "agent": "'.$agentKey.'"},';
            $lines[] = '  "result": {';
            $lines[] = '    "summary": "<kurze Zusammenfassung>",';
            $lines[] = '    "data": {"md_files": []}';
            $lines[] = '  },';
            $lines[] = '  "db_payload": {';
            $lines[] = '    "tables": {';
            $lines[] = '      '.$exampleTables;
            $lines[] = '    }';
            $lines[] = '  }';
            $lines[] = '}';
            $lines[] = '```';
            $lines[] = '';
            $lines[] = 'WICHTIG: `db_payload.tables` darf NUR die oben gelisteten Tabellennamen enthalten.';
            // Phase-specific verboten lists prevent model from reusing other-phase table patterns
            $verbotenExtra = match ($phaseNr) {
                3 => '"p3_cluster", "p3_review_typ_entscheidung", "p3_mapping_suchstring_komponenten", "p3_suchstring_komponenten"',
                4 => '"p4_cluster", "p4_final_search_components", "p4_disziplinen", "p4_final_database_matrix"',
                default => '"p4_final_search_components", "p4_disziplinen"',
            };
            $lines[] = "VERBOTEN (nicht existierende Tabellen): {$verbotenExtra} — diese Namen dürfen NICHT in db_payload.tables erscheinen.";
            $lines[] = '';

            // Exaktes Schema der für diese Phase relevanten Tabellen
            $schema = $this->schemaForPhase($phaseNr);
            if ($schema !== '') {
                $lines[] = '## DB-Schema (exakte Spaltennamen — keine anderen verwenden!)';
                $lines[] = '';
                $lines[] = $schema;
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    private function appendPhaseData(array &$lines, Projekt $projekt, int $phaseNr): void
    {
        if ($phaseNr < 2) {
            return;
        }

        $projekt->load($this->relationsForPhase($phaseNr));

        if ($phaseNr >= 2 && $projekt->relationLoaded('p1Komponenten') && $projekt->p1Komponenten->isNotEmpty()) {
            $lines[] = '## Vorgeladene Phasendaten';
            $lines[] = '';
            $lines[] = '### P1-Komponenten';
            $lines[] = '| Kürzel | Label | Modell |';
            $lines[] = '|--------|-------|--------|';
            foreach ($projekt->p1Komponenten as $k) {
                $lines[] = "| {$k->komponente_kuerzel} | {$k->komponente_label} | {$k->modell} |";
            }
            $lines[] = '';
        }

        if ($phaseNr >= 2 && $projekt->relationLoaded('p1Kriterien') && $projekt->p1Kriterien->isNotEmpty()) {
            $lines[] = '### P1-Kriterien (Ein-/Ausschlusskriterien)';
            $lines[] = '| Typ | Beschreibung |';
            $lines[] = '|-----|-------------|';
            foreach ($projekt->p1Kriterien as $k) {
                $type = $k->kriterium_typ ?? '';
                $desc = $k->beschreibung ?? '';
                $lines[] = "| {$type} | {$desc} |";
            }
            $lines[] = '';
        }

        if ($phaseNr >= 3 && $projekt->relationLoaded('p2Cluster') && $projekt->p2Cluster->isNotEmpty()) {
            $lines[] = '### P2-Cluster';
            foreach ($projekt->p2Cluster as $cluster) {
                $name = $cluster->cluster_label ?? $cluster->cluster_id ?? '';
                $lines[] = "- {$name}";
            }
            $lines[] = '';
        }

        if ($phaseNr >= 4 && $projekt->relationLoaded('p3Datenbankmatrix') && $projekt->p3Datenbankmatrix->isNotEmpty()) {
            $lines[] = '### P3-Datenbanken';
            $lines[] = '| Name | Disziplin | Empfohlen |';
            $lines[] = '|------|-----------|-----------|';
            foreach ($projekt->p3Datenbankmatrix as $db) {
                $lines[] = "| {$db->datenbank} | {$db->disziplin} | ".($db->empfohlen ? 'Ja' : 'Nein').' |';
            }
            $lines[] = '';
        }

        if ($phaseNr >= 5 && $projekt->relationLoaded('p4Suchstrings') && $projekt->p4Suchstrings->isNotEmpty()) {
            $lines[] = '### P4-Suchstrings';
            foreach ($projekt->p4Suchstrings as $s) {
                $lines[] = "- **{$s->datenbank}**: `{$s->suchstring}`";
            }
            $lines[] = '';
        }

        if ($phaseNr >= 5) {
            $trefferCount = $projekt->p5Treffer()->count();
            if ($trefferCount > 0) {
                $lines[] = "### Suchergebnisse: {$trefferCount} importierte Treffer";
                $lines[] = '';
            }
        }

        if ($phaseNr >= 6) {
            // Load treffer with IDs so phase-6 agent can write p6_qualitaetsbewertung (treffer_id FK)
            $treffer = $projekt->p5Treffer()
                ->whereHas('screeningEntscheidungen', fn ($q) => $q->where('entscheidung', 'eingeschlossen'))
                ->select(['id', 'titel', 'autoren', 'jahr'])
                ->limit(50)
                ->get();

            if ($treffer->isNotEmpty()) {
                $lines[] = '### P5-Screening: Eingeschlossene Treffer (für p6_qualitaetsbewertung.treffer_id)';
                $lines[] = '| treffer_id (UUID) | Titel | Autoren | Jahr |';
                $lines[] = '|-------------------|-------|---------|------|';
                foreach ($treffer as $t) {
                    $titel = mb_substr((string) ($t->titel ?? ''), 0, 60);
                    $autoren = mb_substr((string) ($t->autoren ?? ''), 0, 40);
                    $lines[] = "| {$t->id} | {$titel} | {$autoren} | {$t->jahr} |";
                }
            } else {
                $lines[] = '### P5-Screening: 0 eingeschlossene Treffer';
                // Provide ALL treffer as fallback so P6 agent has something to assess
                $allTreffer = $projekt->p5Treffer()
                    ->select(['id', 'titel', 'autoren', 'jahr'])
                    ->limit(20)
                    ->get();
                if ($allTreffer->isNotEmpty()) {
                    $lines[] = '(Alle importierten Treffer ohne Screening-Entscheidung — ebenfalls für p6 nutzbar)';
                    $lines[] = '| treffer_id (UUID) | Titel | Jahr |';
                    $lines[] = '|-------------------|-------|------|';
                    foreach ($allTreffer as $t) {
                        $titel = mb_substr((string) ($t->titel ?? ''), 0, 80);
                        $lines[] = "| {$t->id} | {$titel} | {$t->jahr} |";
                    }
                }
            }
            $lines[] = '';
        }

        if ($phaseNr >= 7 && $projekt->relationLoaded('p6Qualitaetsbewertungen') && $projekt->p6Qualitaetsbewertungen->isNotEmpty()) {
            $lines[] = '### P6-Qualitätsbewertungen';
            $lines[] = "- {$projekt->p6Qualitaetsbewertungen->count()} Studien bewertet";
            $lines[] = '';
        }
    }

    /**
     * Returns the list of writable table names for a given phase.
     *
     * @return string[]
     */
    private function tablesForPhase(int $phaseNr): array
    {
        return array_keys($this->phaseSchemas()[$phaseNr] ?? []);
    }

    /**
     * Canonical phase→table→columns map. Single source of truth for schema injection and table allowlist.
     *
     * @return array<int, array<string, string>>
     */
    private function phaseSchemas(): array
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
            ],
            5 => [
                'p5_treffer' => 'projekt_id (uuid, required), record_id (text, required), titel, autoren, jahr (integer), journal, doi, abstract, datenbank_quelle, ist_duplikat (boolean, required)',
                'p5_screening_kriterien' => 'projekt_id (uuid, required), beschreibung (text, required), level (enum: L1_titel_abstract|L2_volltext, required), kriterium_typ (enum: einschluss|ausschluss, required), beispiel',
                'p5_prisma_zahlen' => 'projekt_id (uuid, required), identifiziert_gesamt (integer), davon_datenbank_treffer (integer), davon_graue_literatur (integer), nach_deduplizierung (integer), ausgeschlossen_l1 (integer), volltext_geprueft (integer), ausgeschlossen_l2 (integer), eingeschlossen_final (integer)',
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
     * Gibt das exakte DB-Schema der für diese Phase beschreibbaren Tabellen zurück.
     * Nur Pflichtfelder (NOT NULL ohne Default) werden als (required) markiert.
     */
    private function schemaForPhase(int $phaseNr): string
    {
        $tables = $this->phaseSchemas()[$phaseNr] ?? [];
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
     */
    private function relationsForPhase(int $phaseNr): array
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
