<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Workspace;
use App\Services\AgentPromptBuilder;
use Illuminate\Support\Facades\Log;

/**
 * Trait for triggering phase agents from Livewire components.
 *
 * Fire-and-forget: dispatches a queue job immediately, tracks pending state via polling.
 * The button stays disabled until the job completes or fails.
 */
trait TriggersPhaseAgent
{
    public bool $agentDispatched = false;

    public function triggerAgent(int $phaseNr): void
    {
        try {
            $config = config('phase_chain');
            if (! isset($config[$phaseNr])) {
                $this->dispatch('notify', type: 'error', message: 'Phase nicht konfiguriert');

                return;
            }

            $configKey = $config[$phaseNr]['agent_config_key'];

            $promptBuilder = app(AgentPromptBuilder::class);
            $systemPrompt = $promptBuilder->buildSystemPrompt($this->projekt, $phaseNr, $configKey);
            $userPrompt = $promptBuilder->buildUserPrompt($this->projekt, $phaseNr);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $context = [
                'source' => 'phase_trigger',
                'projekt_id' => $this->projekt->id,
                'workspace_id' => $this->projekt->workspace_id,
                'workspace_name' => Workspace::find($this->projekt->workspace_id)?->name,
                'phase_nr' => $phaseNr,
                'user_id' => auth()->id(),
                'user_name' => auth()->user()?->name,
            ];

            // Create pending record synchronously so the button stays disabled on reload
            PhaseAgentResult::create([
                'projekt_id' => $this->projekt->id,
                'user_id' => auth()->id(),
                'phase_nr' => $phaseNr,
                'agent_config_key' => $configKey,
                'status' => 'pending',
            ]);

            ProcessPhaseAgentJob::dispatch(
                $this->projekt->id,
                $phaseNr,
                $configKey,
                $messages,
                $context,
            );

            $this->agentDispatched = true;

        } catch (\Throwable $e) {
            Log::error('TriggersPhaseAgent: unerwartete Exception', [
                'phase_nr' => $phaseNr,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            $this->dispatch('notify', type: 'error', message: 'Fehler bei Agent-Aufruf: '.$e->getMessage());
        }
    }

    public function checkAgentStatus(): void
    {
        $stillPending = PhaseAgentResult::where('projekt_id', $this->projekt->id)
            ->where('status', 'pending')
            ->exists();

        if (! $stillPending) {
            $this->agentDispatched = false;
            $this->dispatch('agent-result-ready');
        }
    }
}
