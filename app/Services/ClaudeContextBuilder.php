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
            $lines[] = '## Output-Anforderung';
            $lines[] = '';
            $lines[] = 'Gib exakt EIN gültiges JSON-Objekt zurück (JSON Envelope v1).';
            $lines[] = 'Pflicht-Keys: meta, db, result, next, warnings.';
            $lines[] = 'Keine Markdown-Fences. Kein Text davor oder danach.';
            $lines[] = '';
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
                $lines[] = "| {$db->datenbank_name} | {$db->disziplin} | ".($db->empfohlen ? 'Ja' : 'Nein').' |';
            }
            $lines[] = '';
        }

        if ($phaseNr >= 5 && $projekt->relationLoaded('p4Suchstrings') && $projekt->p4Suchstrings->isNotEmpty()) {
            $lines[] = '### P4-Suchstrings';
            foreach ($projekt->p4Suchstrings as $s) {
                $lines[] = "- **{$s->datenbank}** ({$s->suchstring_typ}): `{$s->suchstring_text}`";
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

        if ($phaseNr >= 6 && $projekt->relationLoaded('p5Treffer')) {
            $eingeschlossen = $projekt->p5Treffer()
                ->whereHas('screeningEntscheidungen', fn ($q) => $q->where('entscheidung', 'eingeschlossen'))
                ->count();
            $lines[] = "### P5-Screening: {$eingeschlossen} eingeschlossene Treffer";
            $lines[] = '';
        }

        if ($phaseNr >= 7 && $projekt->relationLoaded('p6Qualitaetsbewertungen') && $projekt->p6Qualitaetsbewertungen->isNotEmpty()) {
            $lines[] = '### P6-Qualitätsbewertungen';
            $lines[] = "- {$projekt->p6Qualitaetsbewertungen->count()} Studien bewertet";
            $lines[] = '';
        }
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
