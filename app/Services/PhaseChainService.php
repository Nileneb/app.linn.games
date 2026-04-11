<?php

namespace App\Services;

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PhaseChainService
{
    /**
     * Dispatches the next phase agent job if the phase chain config defines a successor.
     * Called after a phase agent job completes successfully.
     */
    public function maybeDispatchNext(Projekt $projekt, int $completedPhaseNr, int|string|null $userId = null): void
    {
        $chain = config("phase_chain.{$completedPhaseNr}");

        if (! is_array($chain)) {
            return; // No successor defined for this phase
        }

        // Quality-Gate: Validate that the previous phase result has substance (Issue #122)
        // Prevents auto-dispatch of next phase when previous agent returned only confirmations
        $lastResult = PhaseAgentResult::where('projekt_id', $projekt->id)
            ->where('phase_nr', $completedPhaseNr)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if (! $lastResult || ! $this->isValidPhaseResult($lastResult)) {
            Log::warning('PhaseChain: skipping auto-dispatch, previous result validation failed', [
                'projekt_id' => $projekt->id,
                'completed_phase' => $completedPhaseNr,
                'content_length' => $lastResult ? mb_strlen(trim($lastResult->content ?? '')) : 0,
            ]);

            return;
        }

        // null means "no auto-chain for this phase" (e.g. P4 requires manual paper import)
        if ($chain['next_phase'] === null) {
            Log::info('PhaseChain: no auto-chain configured for this phase', [
                'projekt_id' => $projekt->id,
                'completed_phase' => $completedPhaseNr,
            ]);

            return;
        }

        $nextPhase = (int) $chain['next_phase'];
        $agentKey = (string) $chain['agent_config_key'];

        // Transition Validation: Check phase thresholds before auto-dispatch
        $validator = app(TransitionValidator::class);
        $validation = $validator->validateTransition($projekt, $completedPhaseNr, $nextPhase);

        if ($validation['is_blocking']) {
            Log::warning('PhaseChain: transition blocked by threshold validation', [
                'projekt_id' => $projekt->id,
                'completed_phase' => $completedPhaseNr,
                'next_phase' => $nextPhase,
                'warning' => $validation['warning'],
                'threshold_details' => $validation['threshold_details'],
            ]);

            return; // Manuelle Freigabe erforderlich
        }

        if (! $validation['can_transition']) {
            Log::info('PhaseChain: transition has warnings but not blocking', [
                'projekt_id' => $projekt->id,
                'completed_phase' => $completedPhaseNr,
                'next_phase' => $nextPhase,
                'warning' => $validation['warning'],
            ]);
            // Wir fahren trotzdem fort (non-blocking warning)
        }

        if (! config("services.anthropic.agents.{$agentKey}")) {
            Log::warning('PhaseChain: next agent not configured, skipping auto-dispatch', [
                'projekt_id' => $projekt->id,
                'completed_phase' => $completedPhaseNr,
                'next_phase' => $nextPhase,
                'agent_config_key' => $agentKey,
            ]);

            return;
        }

        Log::info('PhaseChain: dispatching next phase agent', [
            'projekt_id' => $projekt->id,
            'completed_phase' => $completedPhaseNr,
            'next_phase' => $nextPhase,
            'agent_config_key' => $agentKey,
        ]);

        $messages = $this->buildMessages($projekt, $completedPhaseNr, $nextPhase);

        ProcessPhaseAgentJob::dispatch(
            $projekt->id,
            $nextPhase,
            $agentKey,
            $messages,
            [
                'source' => 'phase_chain_auto',
                'projekt_id' => $projekt->id,
                'workspace_id' => $projekt->workspace_id,
                'phase_nr' => $nextPhase,
                // Aktiver Nutzer wird propagiert; Fallback auf Projekt-Ersteller (Issue #154)
                'user_id' => $userId ?? $projekt->user_id,
                'label' => $chain['label'] ?? "Phase {$nextPhase}",
            ],
        );
    }

    /**
     * Builds the context message array for the chained phase agent.
     * Mirrors the logic in agent-action-button.blade.php::buildContextMessages().
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(Projekt $projekt, int $completedPhaseNr, int $nextPhaseNr): array
    {
        $lines = [];

        $lines[] = '=== PROJEKTKONTEXT ===';
        $lines[] = "Projekt-ID: {$projekt->id}";
        $lines[] = "Forschungsfrage: {$projekt->forschungsfrage}";

        if ($projekt->review_typ) {
            $lines[] = "Review-Typ: {$projekt->review_typ}";
        }

        // Include completed previous phase results (up to and including the just-completed phase)
        $previousResults = PhaseAgentResult::where('projekt_id', $projekt->id)
            ->where('phase_nr', '<=', $completedPhaseNr)
            ->where('status', 'completed')
            ->whereNotNull('content')
            ->orderBy('phase_nr')
            ->orderByDesc('created_at')
            ->get()
            ->unique('phase_nr');

        if ($previousResults->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '=== ERGEBNISSE VORHERIGER PHASEN ===';
            foreach ($previousResults as $result) {
                $lines[] = "--- Phase {$result->phase_nr} ---";
                $lines[] = mb_substr((string) $result->content, 0, 4000);
            }
        }

        // Available documents
        $paperCount = (int) DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM paper_embeddings WHERE projekt_id = ?::uuid',
            [$projekt->id],
        )?->cnt;

        $trefferCount = $projekt->p5Treffer()->count();

        if ($paperCount > 0 || $trefferCount > 0) {
            $lines[] = '';
            $lines[] = '=== VORHANDENE DOKUMENTE ===';
            if ($paperCount > 0) {
                $lines[] = "Indexierte Dokument-Abschnitte (paper_embeddings): {$paperCount}";
            }
            if ($trefferCount > 0) {
                $lines[] = "Importierte Treffer (p5_treffer): {$trefferCount}";
            }
        }

        $lines[] = '';
        $lines[] = "Aktuelle Phase: P{$nextPhaseNr}";
        $lines[] = 'Bitte nutze die oben genannte Projekt-ID für alle DB-Operationen (SET LOCAL app.current_projekt_id).';
        $lines[] = "Diese Phase wurde automatisch nach Abschluss von Phase {$completedPhaseNr} gestartet.";

        return [
            ['role' => 'user', 'content' => implode("\n", $lines)],
        ];
    }

    /**
     * Validates that a phase result contains meaningful content (not just confirmations).
     * Prevents chain dispatch when agent returns only "Okay", "understood", etc. (Issue #122)
     *
     * @return bool True if result is valid and substantial enough for chain dispatch
     */
    private function isValidPhaseResult(PhaseAgentResult $result): bool
    {
        $content = trim($result->content ?? '');

        // Minimum length check: at least 100 characters of substance
        if (mb_strlen($content) < 100) {
            return false;
        }

        // Pattern-based filter: block confirmation-only responses
        $confirmationPatterns = [
            '/^(okay|ok|understood|i understand|understood|will proceed)/i',
            '/^(i will use|i\'ll use|let me|lass mich)/i',
            '/^(acknowledged|copy that|roger|confirmed)/i',
        ];

        foreach ($confirmationPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false; // Content starts with a confirmation, not actual work
            }
        }

        return true;
    }

    /**
     * Prüft ob eine Phase stuck ist (3+ failed PhaseAgentResults).
     */
    public function detectStuck(Projekt $projekt, int $phaseNr): bool
    {
        $failedCount = PhaseAgentResult::where('projekt_id', $projekt->id)
            ->where('phase_nr', $phaseNr)
            ->where('status', 'failed')
            ->count();

        return $failedCount >= 3;
    }
}
