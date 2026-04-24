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

        $this->appendPhaseData($lines, $projekt, $phaseNr);

        if ($context['structured_output'] ?? false) {
            $phaseNr = (int) ($context['phase_nr'] ?? 0);
            $agentKey = $context['agent_config_key'] ?? 'worker';

            $lines[] = '## Output-Anforderung';
            $lines[] = '';
            $lines[] = 'REGEL: Kein SQL. Kein SELECT. Nur neue INSERT-Daten in db_payload.tables.';
            $lines[] = '';
            $allowedTables = PhaseSchemaRegistry::tablesForPhase($phaseNr);
            if (! empty($allowedTables)) {
                $lines[] = '**ERLAUBTE TABELLEN FÜR DIESE PHASE (NUR DIESE — keine anderen Tabellennamen verwenden!):**';
                foreach ($allowedTables as $tbl) {
                    $lines[] = "- `{$tbl}`";
                }
                $lines[] = '';
            }

            $lines[] = 'Gib exakt EIN gültiges JSON-Objekt zurück. Kein Text davor oder danach. Keine Markdown-Fences.';
            $lines[] = '';
            $exampleTables = '';
            if (! empty($allowedTables)) {
                $firstTable = $allowedTables[0];
                $projektIdEx = (string) ($context['projekt_id'] ?? 'PROJEKT-UUID');
                $exampleTables = '"'.$firstTable.'": [{"projekt_id": "'.$projektIdEx.'", "...weitere Felder laut Schema...": "..."}]';
            }
            if ($phaseNr === 1) {
                $lines[] = '## Qualitätsbewertung der Forschungsfrage (P1)';
                $lines[] = 'Bewerte die Forschungsfrage streng nach dieser Rubrik — nutze die VOLLE Skala 0–100:';
                $lines[] = '- **0–39 → schwach**: Fragestellung zu vage, keine erkennbaren PICO/SPIDER-Komponenten, kein klares Outcome oder Population';
                $lines[] = '- **40–59 → befriedigend**: Grundstruktur erkennbar, aber ≥2 wichtige Komponenten fehlen oder sind zu unspezifisch';
                $lines[] = '- **60–79 → gut**: Klare Fragestellung, die meisten Komponenten erkennbar, leichte Unschärfen tolerierbar';
                $lines[] = '- **80–100 → sehr_gut**: Vollständig strukturiert, alle Kernkomponenten explizit, Outcome messbar, Population klar abgegrenzt';
                $lines[] = 'Das `level`-Feld MUSS dem Score entsprechen (nicht frei wählen): sehr_gut=80–100, gut=60–79, befriedigend=40–59, schwach=0–39.';
                $lines[] = '';
            }
            $lines[] = 'Pflichtstruktur (EXAKT so — keine anderen Schlüssel!):';
            $lines[] = '```json';
            $lines[] = '{';
            if ($phaseNr === 1) {
                $lines[] = '  "meta": {';
                $lines[] = '    "phase": '.$phaseNr.',';
                $lines[] = '    "agent": "'.$agentKey.'",';
                $lines[] = '    "qualitaets_bewertung": {';
                $lines[] = '      "score": <integer 0-100 gemäß Rubrik>,';
                $lines[] = '      "level": "<schwach|befriedigend|gut|sehr_gut — MUSS zum Score passen>",';
                $lines[] = '      "punkte": ["+ stärke1", "- schwäche1"]';
                $lines[] = '    }';
                $lines[] = '  },';
            } else {
                $lines[] = '  "meta": {"phase": '.$phaseNr.', "agent": "'.$agentKey.'"},';
            }
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
            $verbotenExtra = match ($phaseNr) {
                3 => '"p3_cluster", "p3_review_typ_entscheidung", "p3_mapping_suchstring_komponenten", "p3_suchstring_komponenten"',
                4 => '"p4_cluster", "p4_final_search_components", "p4_disziplinen", "p4_final_database_matrix"',
                default => '"p4_final_search_components", "p4_disziplinen"',
            };
            $lines[] = "VERBOTEN (nicht existierende Tabellen): {$verbotenExtra} — diese Namen dürfen NICHT in db_payload.tables erscheinen.";
            $lines[] = '';

            $schema = PhaseSchemaRegistry::schemaForPhase($phaseNr);
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

        $projekt->load(PhaseSchemaRegistry::relationsForPhase($phaseNr));

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
}
