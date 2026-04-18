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
    public bool $deferred = false;
    public string $deferredMessage = '';
    public bool $outOfCredits = false;

    public function mount(): void
    {
        $latest = PhaseAgentResult::where('projekt_id', $this->projekt->id)
            ->where('phase_nr', $this->phaseNr)
            ->where('agent_config_key', $this->agentConfigKey)
            ->whereIn('status', ['pending', 'deferred', 'out_of_credits'])
            ->orderByDesc('created_at')
            ->first();

        $this->dispatched = $latest?->status === 'pending';
        $this->deferred = $latest?->status === 'deferred';
        $this->deferredMessage = $latest?->error_message ?? '';
        $this->outOfCredits = $latest?->status === 'out_of_credits';
    }

    public function checkStatus(): void
    {
        $stillPending = PhaseAgentResult::where('projekt_id', $this->projekt->id)
            ->where('phase_nr', $this->phaseNr)
            ->where('agent_config_key', $this->agentConfigKey)
            ->where('status', 'pending')
            ->exists();

        if (! $stillPending) {
            $this->dispatched = false;
            $this->deferred = PhaseAgentResult::where('projekt_id', $this->projekt->id)
                ->where('phase_nr', $this->phaseNr)
                ->where('agent_config_key', $this->agentConfigKey)
                ->where('status', 'deferred')
                ->exists();
            $this->outOfCredits = PhaseAgentResult::where('projekt_id', $this->projekt->id)
                ->where('phase_nr', $this->phaseNr)
                ->where('agent_config_key', $this->agentConfigKey)
                ->where('status', 'out_of_credits')
                ->exists();
            $this->dispatch('agent-result-ready', phaseNr: $this->phaseNr);
        }
    }

    public function runAgent(): void
    {
        try {
            $messages = $this->buildContextMessages();

            // Create pending record BEFORE dispatching so page reloads show the spinner
            PhaseAgentResult::create([
                'projekt_id'       => $this->projekt->id,
                'user_id'          => auth()->id(),
                'phase_nr'         => $this->phaseNr,
                'agent_config_key' => $this->agentConfigKey,
                'status'           => 'pending',
            ]);

            ProcessPhaseAgentJob::dispatch(
                $this->projekt->id,
                $this->phaseNr,
                $this->agentConfigKey,
                $messages,
                [
                    'source'         => 'recherche_phase_agent',
                    'projekt_id'     => $this->projekt->id,
                    'workspace_id'   => $this->projekt->workspace_id,
                    'workspace_name' => \App\Models\Workspace::find($this->projekt->workspace_id)?->name,
                    'phase_nr'       => $this->phaseNr,
                    'user_id'        => auth()->id(),
                    'user_name'      => auth()->user()?->name,
                    'label'          => $this->label,
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

<div @if($dispatched) wire:poll.5s="checkStatus" @endif>
    @if ($dispatched)
        <p class="inline-flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
            <svg class="h-4 w-4 animate-spin text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            {{ __('KI läuft im Hintergrund…') }}
        </p>
    @elseif ($deferred)
        <p class="inline-flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            {{ $deferredMessage ?: __('Tageslimit — automatischer Retry morgen um 00:05') }}
        </p>
    @elseif ($outOfCredits)
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Guthaben aufgebraucht</p>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                        Diese Phase wurde abgebrochen, weil dein Guthaben nicht ausreichte.
                        Lade Guthaben auf um fortzufahren.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('credits.purchase') }}"
                           class="inline-flex items-center gap-1.5 rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-amber-700">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Guthaben aufladen
                        </a>
                        <button wire:click="runAgent"
                                class="inline-flex items-center rounded-md border border-amber-400 px-3 py-1.5 text-sm text-amber-700 transition hover:bg-amber-100 dark:border-amber-600 dark:text-amber-300 dark:hover:bg-amber-900">
                            Erneut versuchen
                        </button>
                    </div>
                </div>
            </div>
        </div>
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
