<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Actions\SendAgentMessage;
use App\Models\PhaseAgentResult;
use App\Services\AgentPromptBuilder;
use Illuminate\Support\Facades\Log;

/**
 * Trait for triggering phase agents from Livewire components.
 *
 * Usage: add `use TriggersPhaseAgent;` to phase component and call `$this->triggerAgent(phaseNr)`
 */
trait TriggersPhaseAgent
{
    /**
     * Trigger an agent for the given phase.
     *
     * @param  int  $phaseNr  Phase number (1-7)
     */
    public function triggerAgent(int $phaseNr): void
    {
        try {
            // Get agent config key from phase_chain.php
            $config = config('phase_chain');
            if (! isset($config[$phaseNr])) {
                $this->dispatch('notify', type: 'error', message: 'Phase nicht konfiguriert');

                return;
            }

            $agentConfig = $config[$phaseNr];
            $configKey = $agentConfig['agent_config_key'];

            // Build context messages
            $promptBuilder = app(AgentPromptBuilder::class);
            $systemPrompt = $promptBuilder->buildSystemPrompt($this->projekt, $phaseNr, $configKey);
            $userPrompt = $promptBuilder->buildUserPrompt($this->projekt, $phaseNr);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            // Kontext für RLS-Bootstrap aufbauen: projekt_id, workspace_id, user_id, phase_nr
            // user_id = aktuell eingeloggter Nutzer (nicht Projekt-Ersteller) — Issue #154
            $context = [
                'projekt_id' => $this->projekt->id,
                'workspace_id' => $this->projekt->workspace_id,
                'user_id' => auth()->id(),
                'phase_nr' => $phaseNr,
            ];

            // Agent aufrufen mit Kontext, damit LangdockContextInjector SET LOCAL injizieren kann
            $sendAgent = app(SendAgentMessage::class);
            $result = $sendAgent->execute($configKey, $messages, 120, $context);

            if (! $result['success']) {
                Log::warning('Agent execution failed', [
                    'phase_nr' => $phaseNr,
                    'config_key' => $configKey,
                    'content' => $result['content'],
                ]);
                $this->dispatch('notify', type: 'warning', message: $result['content']);

                return;
            }

            // Save result
            PhaseAgentResult::create([
                'projekt_id' => $this->projekt->id,
                'user_id' => auth()->id(),
                'phase_nr' => $phaseNr,
                'agent_config_key' => $configKey,
                'status' => 'completed',
                'content' => $result['content'],
            ]);

            Log::info('Agent result saved', [
                'phase_nr' => $phaseNr,
                'projekt_id' => $this->projekt->id,
            ]);

            $this->dispatch('notify', type: 'success', message: 'KI-Vorschlag generiert ✨');

        } catch (\Throwable $e) {
            Log::error('TriggersPhaseAgent: unerwartete Exception', [
                'phase_nr' => $phaseNr,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            $this->dispatch('notify', type: 'error', message: 'Fehler bei Agent-Aufruf: '.$e->getMessage());
        }
    }
}
