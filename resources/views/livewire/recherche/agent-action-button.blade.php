<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Services\AgentPromptBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;
    public string $agentConfigKey;
    public string $label;
    public int $phaseNr;

    public bool $dispatched = false;

    public function runAgent(): void
    {
        try {
            $messages = $this->buildContextMessages();

            ProcessPhaseAgentJob::dispatch(
                $this->projekt->id,
                $this->phaseNr,
                $this->agentConfigKey,
                $messages,
                [
                    'source' => 'recherche_phase_agent',
                    'projekt_id' => $this->projekt->id,
                    'workspace_id' => $this->projekt->workspace_id,
                    'phase_nr' => $this->phaseNr,
                    'user_id' => $this->projekt->user_id,
                    'label' => $this->label,
                ]
            );

            $this->dispatched = true;
        } catch (\Throwable $e) {
            Log::error('runAgent failed', [
                'projekt_id' => $this->projekt->id,
                'phase_nr' => $this->phaseNr,
                'agent_config_key' => $this->agentConfigKey,
                'exception' => $e->getMessage(),
            ]);

            // Zeige dem User-Fehler-Feedback statt stillem Hang
            $this->dispatch('notify', type: 'error', message: __('Der KI-Agent konnte nicht gestartet werden. Bitte versuche es erneut.'));
        }
    }

    protected function buildContextMessages(): array
    {
        $promptBuilder = app(AgentPromptBuilder::class);

        // System prompt mit Phase-Guidance, Thresholds, Templates
        $systemPrompt = $promptBuilder->buildSystemPrompt(
            $this->projekt,
            $this->phaseNr,
            $this->agentConfigKey
        );

        // Sammle Vorphasen-Ergebnisse für Kontext
        $previousResults = rescue(
            fn () => PhaseAgentResult::where('projekt_id', $this->projekt->id)
                ->where('phase_nr', '<', $this->phaseNr)
                ->where('status', 'completed')
                ->whereNotNull('content')
                ->orderBy('phase_nr')
                ->orderByDesc('created_at')
                ->get()
                ->unique('phase_nr')
                ->mapWithKeys(fn ($r) => [$r->phase_nr => $r->content])
                ->all(),
            [],
            report: true,
        );

        // Enhanced user prompt mit aktuellem Status
        $userPrompt = $promptBuilder->buildUserPrompt(
            $this->projekt,
            $this->phaseNr,
            $previousResults
        );

        // Zusätzliche kritische Informationen
        $lines = [
            $userPrompt,
            '',
            '=== DATENBANKZUGRIFF ===',
            "Projekt-ID für alle Operationen: {$this->projekt->id}",
            'SET LOCAL app.current_projekt_id = \'' . $this->projekt->id . '\';',
        ];

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => implode("\n", $lines)],
        ];
    }
}; ?>

<div>
    @if ($dispatched)
        <p class="inline-flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
            <svg class="h-4 w-4 animate-spin text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            {{ __('KI läuft im Hintergrund…') }}
        </p>
    @else
        <button
            wire:click="runAgent"
            wire:loading.attr="disabled"
            wire:target="runAgent"
            :disabled="$dispatched"
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="runAgent">{{ $label }}</span>
            <span wire:loading wire:target="runAgent" class="inline-flex items-center gap-1">
                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                {{ __('Wird gestartet…') }}
            </span>
        </button>
    @endif
</div>
