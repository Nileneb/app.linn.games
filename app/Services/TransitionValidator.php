<?php

namespace App\Services;

use App\Data\TransitionStatus;
use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Config;

/**
 * TransitionValidator
 * Validiert Phase-Übergänge basierend auf Schwellwerten und optionalen Agent-Checks.
 * Implementiert hybride Validierung: Database-Driven + Agent-Augmented.
 */
class TransitionValidator
{
    public function __construct(
        private PhaseCountService $countService,
    ) {}

    /**
     * Validiert einen Phase-Übergang
     *
     * @return array{
     *     can_transition: bool,
     *     is_blocking: bool,
     *     warning: string|null,
     *     counts: array,
     *     threshold_details: array
     * }
     */
    public function validateTransition(Projekt $projekt, int $fromPhase, int $toPhase): array
    {
        $config = Config::get("phase_chain.thresholds.{$fromPhase}");

        if (! is_array($config)) {
            return [
                'can_transition' => true,
                'is_blocking' => false,
                'warning' => null,
                'counts' => [],
                'threshold_details' => [],
            ];
        }

        $counts = $this->countService->getAllCounts($projekt);
        $currentCounts = $counts[$fromPhase] ?? [];

        // Zähle Schwellwerte ab
        $thresholdDetails = $this->checkThresholds($fromPhase, $currentCounts, $config);

        // Agent-Check wenn konfiguriert
        $agentCheckPassed = true;
        if ($config['agent_check'] ?? false) {
            $agentCheckPassed = $this->performAgentCheck($projekt, $fromPhase, $toPhase, $currentCounts);
        }

        $allChecksPass = $thresholdDetails['all_pass'] && $agentCheckPassed;
        $isBlocking = ($config['blocking'] ?? false) && ! $allChecksPass;

        return [
            'can_transition' => $allChecksPass || ! ($config['blocking'] ?? false),
            'is_blocking' => $isBlocking,
            'warning' => ! $allChecksPass ? ($config['warning'] ?? null) : null,
            'counts' => $currentCounts,
            'threshold_details' => $thresholdDetails,
        ];
    }

    /**
     * Prüft alle Schwellwerte für eine Phase
     */
    private function checkThresholds(int $phase, array $counts, array $config): array
    {
        $details = [];
        $allPass = true;

        foreach (['min_components', 'min_cluster', 'min_mapping', 'min_databases', 'min_searchstrings', 'min_treffer', 'min_assessments', 'min_extractions'] as $key) {
            if (! isset($config[$key])) {
                continue;
            }

            $threshold = $config[$key];
            $countKey = str_replace('min_', '', $key);

            // Map count keys to actual counts
            $currentCount = match ($countKey) {
                'components' => $counts['komponenten'] ?? 0,
                'cluster' => $counts['cluster'] ?? 0,
                'mapping' => $counts['mappings'] ?? 0,
                'databases' => $counts['datenbanken'] ?? 0,
                'searchstrings' => $counts['suchstrings'] ?? 0,
                'treffer' => $counts['treffer'] ?? 0,
                'assessments' => $counts['bewertungen'] ?? 0,
                'extractions' => $counts['extraktionen'] ?? 0,
                default => 0,
            };

            $passed = $currentCount >= $threshold;

            $details[$key] = [
                'threshold' => $threshold,
                'current' => $currentCount,
                'passed' => $passed,
            ];

            if (! $passed) {
                $allPass = false;
            }
        }

        $details['all_pass'] = $allPass;

        return $details;
    }

    /**
     * Führt einen Agent-Check durch (z.B. Qualitätsprüfung)
     * Diese Implementierung ist placeholder; real würde das über ClaudeService laufen
     */
    private function performAgentCheck(Projekt $projekt, int $fromPhase, int $toPhase, array $counts): bool
    {
        // Placeholder: Agent würde hier z.B. Suchstring-Qualität prüfen
        // Für jetzt: True wenn minimale Anzahl vorhanden
        return ($counts['suchstrings'] ?? 0) >= 1;
    }

    /**
     * Gibt User-freundliche Warnung zurück
     */
    public function getWarningMessage(array $validationResult): ?string
    {
        return $validationResult['warning'];
    }

    /**
     * Prüft ob Transition möglich ist (unter Berücksichtigung von Blocking-Regeln)
     */
    public function canTransition(Projekt $projekt, int $fromPhase, int $toPhase): bool
    {
        $result = $this->validateTransition($projekt, $fromPhase, $toPhase);

        return $result['can_transition'];
    }

    /**
     * Prüft ob Transition blockiert ist (manuelle Freigabe nötig)
     */
    public function isBlocking(Projekt $projekt, int $fromPhase, int $toPhase): bool
    {
        $result = $this->validateTransition($projekt, $fromPhase, $toPhase);

        return $result['is_blocking'];
    }

    /**
     * Gibt ein TransitionStatus DTO für die UI zurück
     */
    public function getTransitionStatus(Projekt $projekt, int $fromPhase, int $toPhase): TransitionStatus
    {
        $validation = $this->validateTransition($projekt, $fromPhase, $toPhase);

        return TransitionStatus::fromValidation($validation);
    }
}
