<?php

namespace App\Services;

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Log;

class WorkerCloneService
{
    public function __construct(
        private readonly CreditService $creditService,
    ) {}

    /**
     * Determine whether a clone/retry should be dispatched for this result.
     *
     * Returns false for non-failed results. Returns true when the cumulative
     * failed count for the same project + phase reaches 3 or more.
     */
    public function shouldClone(PhaseAgentResult $result, Projekt $projekt): bool
    {
        if ($result->status !== 'failed') {
            return false;
        }

        $failedCount = PhaseAgentResult::where('projekt_id', $projekt->id)
            ->where('phase_nr', $result->phase_nr)
            ->where('status', 'failed')
            ->count();

        return $failedCount >= 3;
    }

    /**
     * Dispatch a cloned ProcessPhaseAgentJob for the given failed result.
     *
     * Checks the workspace clone limit first — throws CloneLimitExceededException
     * if the workspace has reached its tier limit of concurrent pending jobs.
     *
     * @throws \App\Exceptions\CloneLimitExceededException
     */
    public function clone(PhaseAgentResult $result, Projekt $projekt, string $strategy = 'retry'): void
    {
        $workspace = $projekt->workspace;

        // Tier-Limit prüfen — wirft CloneLimitExceededException bei Überschreitung
        $this->creditService->checkCloneLimit($workspace);

        $agentConfigKey = $result->agent_config_key ?? 'scoping_mapping_agent';

        $messages = [
            [
                'role'    => 'user',
                'content' => "Retry strategy: {$strategy}. Please re-run phase {$result->phase_nr} analysis.",
            ],
        ];

        $context = [
            'user_id'    => $result->user_id ?? $projekt->user_id,
            'projekt_id' => $projekt->id,
            'strategy'   => $strategy,
            'clone'      => true,
        ];

        Log::info('WorkerCloneService: dispatching clone', [
            'projekt_id'       => $projekt->id,
            'phase_nr'         => $result->phase_nr,
            'agent_config_key' => $agentConfigKey,
            'strategy'         => $strategy,
        ]);

        ProcessPhaseAgentJob::dispatch(
            $projekt->id,
            $result->phase_nr,
            $agentConfigKey,
            $messages,
            $context,
        );
    }
}
