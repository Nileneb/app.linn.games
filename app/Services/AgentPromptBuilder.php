<?php

namespace App\Services;

use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Config;

/**
 * AgentPromptBuilder
 * Erstellt kontextbewusste Prompts für Agenten, die Phase-Schwellwerte und 
 * Best-Practices einbeziehen.
 */
class AgentPromptBuilder
{
    public function __construct(
        private readonly PhaseCountService $countService,
        private readonly TransitionValidator $validator,
    ) {}

    /**
     * Erstellt einen erweiterten System-Prompt für einen Agenten basierend auf Phase
     */
    public function buildSystemPrompt(Projekt $projekt, int $phase, string $agentKey): string
    {
        $lines = [
            '# SYSTEM-ANWEISUNG FÜR KI-AGENTEN',
            '',
            "Du bist ein Forschungs-Agent für systematische Literaturreviews (Phase P{$phase}).",
            "Projekt: {$projekt->forschungsfrage}",
            '',
            $this->buildPhaseGuidance($phase),
            '',
            $this->buildThresholdGuidance($projekt, $phase),
            '',
            $this->buildTemplateGuidance($phase),
            '',
            '## CRITICAL REMINDERS',
            '- Alle Datenbankoperationen verwenden: SET LOCAL app.current_projekt_id = \'' . $projekt->id . '\'',
            '- Keine Bestätigungen ohne tatsächliche Arbeit ("okay", "understood", etc.)',
            '- Fokus auf SUBSTANZIELLE ERGEBNISSE, nicht Checklisten',
        ];

        return implode("\n", array_filter($lines));
    }

    /**
     * Gibt Phase-spezifische Anleitungen zurück
     */
    private function buildPhaseGuidance(int $phase): string
    {
        return match ($phase) {
            1 => '## PHASE 1: KOMPONENTEN & STRUKTURMODELL' . "\n" .
                 'Ziel: Finde **mind. 3 Komponenten** der Forschungsfrage (Intervention, Population, Outcome)' . "\n" .
                 'Dies ist notwendig, um zu Phase 2 überzugehen (kein Hard Block, aber starke Empfehlung).',
            2 => '## PHASE 2: CLUSTER & MAPPING' . "\n" .
                 'Ziel: Ordne P1-Komponenten zu Clustern; erstelle Suchstring-Komponenten-Mappings' . "\n" .
                 'Keine strikten Schwellwerte — Warnung wird ausgegeben, wenn keine Cluster/Mappings vorhanden.',
            3 => '## PHASE 3: DATENBANKMATRIX' . "\n" .
                 'Ziel: Erstelle eine Matrix mit verfügbaren Datenbanken, Disziplinen, geograph. Filtern' . "\n" .
                 'Keine strikten Schwellwerte — aber mind. 1 DB sollte ausgewählt sein für P4.',
            4 => '## PHASE 4: SUCHSTRINGS' . "\n" .
                 'Ziel: Generiere Suchstrings basierend auf P3-Datenbankmatrix' . "\n" .
                 'Minimum: 1 String pro Datenbank. Qualitätscheck erfolgt separat.',
            5 => '## PHASE 5: SCREENING & IMPORT' . "\n" .
                 'Ziel: Importiere Papers und führe Screening durch' . "\n" .
                 'Empfehlter Schwellwert: >5 Treffer für sinnvolles Screening in Phase 6.',
            6 => '## PHASE 6: QUALITÄTSBEWERTUNG' . "\n" .
                 'Ziel: Bewerte Paper-Qualität (z.B. GRADE)' . "\n" .
                 'Hard Block: Mind. 1 Bewertung erforderlich, bevor zu Phase 7.',
            7 => '## PHASE 7: DATENEXTRAKTION & SYNTHESE' . "\n" .
                 'Ziel: Extrahiere Daten aus Papers; wähle Synthesemethode' . "\n" .
                 'Hard Block: Mind. 1 Extraktion erforderlich, bevor zu Phase 8.',
            8 => '## PHASE 8: FINAL REPORT' . "\n" .
                 'Ziel: Erstelle Suchprotokoll & Report-Dokumentation' . "\n" .
                 'Finale Phase — fokussiere auf Vollständigkeit und Lesbarkeit.',
            default => '',
        };
    }

    /**
     * Gibt aktuelle Schwellwerte und Status für die Phase aus
     */
    private function buildThresholdGuidance(Projekt $projekt, int $phase): string
    {
        $thresholds = Config::get("phase_chain.thresholds.{$phase}", []);

        if (empty($thresholds)) {
            return '';
        }

        $counts = $this->countService->getAllCounts($projekt);
        $phaseCount = $counts[$phase] ?? [];

        $lines = ['## AKTUELLE SCHWELLWERTE FÜR DIESE PHASE'];

        foreach (['min_components', 'min_cluster', 'min_mapping', 'min_databases', 'min_searchstrings', 'min_treffer', 'min_assessments', 'min_extractions'] as $key) {
            if (! isset($thresholds[$key])) {
                continue;
            }

            $countKey = str_replace('min_', '', $key);
            $current = match ($countKey) {
                'components' => $phaseCount['komponenten'] ?? 0,
                'cluster' => $phaseCount['cluster'] ?? 0,
                'mapping' => $phaseCount['mappings'] ?? 0,
                'databases' => $phaseCount['datenbanken'] ?? 0,
                'searchstrings' => $phaseCount['suchstrings'] ?? 0,
                'treffer' => $phaseCount['treffer'] ?? 0,
                'assessments' => $phaseCount['bewertungen'] ?? 0,
                'extractions' => $phaseCount['extraktionen'] ?? 0,
                default => 0,
            };

            $threshold = $thresholds[$key];
            $status = $current >= $threshold ? '✓' : '✗';
            $blocking = $thresholds['blocking'] ?? false ? '[BLOCK]' : '[WARN]';

            $lines[] = "{$status} {$blocking} {$countKey}: {$current}/{$threshold}";
        }

        return implode("\n", $lines);
    }

    /**
     * Gibt verfügbare Templates für die Phase aus
     */
    private function buildTemplateGuidance(int $phase): string
    {
        $templates = match ($phase) {
            3 => [
                'Template: Datenbankmatrix',
                '```markdown',
                '| Datenbank | Disziplin | Geo-Filter | Relevant? |',
                '|-----------|-----------|-----------|-----------|',
                '| PubMed | Health | Worldwide | ✓ |',
                '| ...',
                '```',
            ],
            5 => [
                'Template: Screening-Einrichtung',
                '```markdown',
                '## Screening Tool: [Rayyan / Covidence / Intern]',
                '## Include Criteria',
                '- ...',
                '## Exclude Criteria',
                '- ...',
                '## Initial Hits: X',
                '```',
            ],
            7 => [
                'Template: Synthese-Methoden',
                '```markdown',
                '## Chosen Synthesis Method',
                '- Meta-Analysis (Quantitativ)',
                '- Thematic Synthesis (Qualitativ)',
                '- Mixed Methods',
                '',
                '## Rationale',
                '...',
                '```',
            ],
            8 => [
                'Template: Suchprotokoll (auto-generiert aus P1-P7)',
                '```markdown',
                '# SEARCH PROTOCOL',
                '## 1. Research Question (P1)',
                '## 2. Components (P1)',
                '## 3. Database Matrix (P3)',
                '## 4. Search Strings (P4)',
                '## 5. Hits & Screening (P5)',
                '## 6. Quality Assessment (P6)',
                '## 7. Data Extraction & Synthesis (P7)',
                '```',
            ],
            default => [],
        };

        if (empty($templates)) {
            return '';
        }

        return "## TEMPLATE FÜR DIESE PHASE\n" . implode("\n", $templates);
    }

    /**
     * Erstellt erweiterte User-Nachricht mit aktuellen Projekt-Daten
     */
    public function buildUserPrompt(Projekt $projekt, int $phase, array $previousResults = []): string
    {
        $lines = [
            '=== USER-ANFRAGE ===',
            '',
            "Arbeite an Phase P{$phase} für Projekt: {$projekt->forschungsfrage}",
            '',
        ];

        if (! empty($previousResults)) {
            $lines[] = '=== VORHERIGE PHASEN-ERGEBNISSE ===';
            foreach ($previousResults as $prevPhase => $result) {
                $lines[] = "--- Phase P{$prevPhase} ---";
                $lines[] = substr($result, 0, 300) . (strlen($result) > 300 ? '...' : '');
                $lines[] = '';
            }
        }

        $counts = $this->countService->getAllCounts($projekt);
        $lines[] = '=== AKTUELLER STATUS ===';
        foreach ($counts as $p => $data) {
            $summary = implode(', ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($data), array_values($data)));
            $lines[] = "Phase P{$p}: {$summary}";
        }

        return implode("\n", $lines);
    }
}
