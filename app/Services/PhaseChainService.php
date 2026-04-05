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
    public function maybeDispatchNext(Projekt $projekt, int $completedPhaseNr): void
    {
        $chain = config("phase_chain.{$completedPhaseNr}");

        if (! is_array($chain)) {
            return; // No successor defined for this phase
        }

        $nextPhase = (int) $chain['next_phase'];
        $agentKey  = (string) $chain['agent_config_key'];

        if (! config("services.langdock.{$agentKey}")) {
            Log::warning('PhaseChain: next agent not configured, skipping auto-dispatch', [
                'projekt_id'        => $projekt->id,
                'completed_phase'   => $completedPhaseNr,
                'next_phase'        => $nextPhase,
                'agent_config_key'  => $agentKey,
            ]);
            return;
        }

        Log::info('PhaseChain: dispatching next phase agent', [
            'projekt_id'       => $projekt->id,
            'completed_phase'  => $completedPhaseNr,
            'next_phase'       => $nextPhase,
            'agent_config_key' => $agentKey,
        ]);

        $messages = $this->buildMessages($projekt, $completedPhaseNr, $nextPhase);

        ProcessPhaseAgentJob::dispatch(
            $projekt->id,
            $nextPhase,
            $agentKey,
            $messages,
            [
                'source'       => 'phase_chain_auto',
                'projekt_id'   => $projekt->id,
                'workspace_id' => $projekt->workspace_id,
                'phase_nr'     => $nextPhase,
                'user_id'      => $projekt->user_id,
                'label'        => $chain['label'] ?? "Phase {$nextPhase}",
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
                $lines[] = mb_substr((string) $result->content, 0, 800);
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
        $lines[] = "Bitte nutze die oben genannte Projekt-ID für alle DB-Operationen (SET LOCAL app.current_projekt_id).";
        $lines[] = "Diese Phase wurde automatisch nach Abschluss von Phase {$completedPhaseNr} gestartet.";

        return [
            ['role' => 'user', 'content' => implode("\n", $lines)],
        ];
    }
}
