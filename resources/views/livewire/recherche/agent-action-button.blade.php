<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;
    public string $agentConfigKey;
    public string $label;
    public int $phaseNr;

    public bool $showModal = false;
    public ?string $loadingPhase = null;

    public function runAgent(): void
    {
        $this->loadingPhase = (string) $this->phaseNr;

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

            $this->showModal = true;
        } catch (\Throwable $e) {
            $this->loadingPhase = null;
            $this->dispatch('notify', type: 'error', message: __('KI-Agent konnte nicht gestartet werden. Bitte versuche es erneut.'));
            Log::error('runAgent failed', [
                'projekt_id' => $this->projekt->id,
                'phase_nr' => $this->phaseNr,
                'agent_config_key' => $this->agentConfigKey,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    #[Computed]
    public function latestResult(): ?PhaseAgentResult
    {
        try {
            return PhaseAgentResult::latestPending(
                $this->projekt->id,
                $this->phaseNr,
                $this->agentConfigKey
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function pollForResult(): void
    {
        $result = $this->latestResult();

        if ($result && $result->status !== 'pending') {
            $this->loadingPhase = null;
        }
    }

    public function acceptResult(): void
    {
        $result = $this->latestResult();
        if ($result && $result->status === 'completed') {
            $this->dispatch('agent-result-accepted', result: $result->content, phaseNr: $this->phaseNr);
            $this->dismissResult();
        }
    }

    public function dismissResult(): void
    {
        $this->showModal = false;
        $this->loadingPhase = null;
    }

    protected function buildContextMessages(): array
    {
        $lines = [];

        // --- Projekt-Identifikation (explizit im User-Message, nicht nur im System-Context) ---
        $lines[] = "=== PROJEKTKONTEXT ===";
        $lines[] = "Projekt-ID: {$this->projekt->id}";
        $lines[] = "Forschungsfrage: {$this->projekt->forschungsfrage}";

        if ($this->projekt->review_typ) {
            $lines[] = "Review-Typ: {$this->projekt->review_typ}";
        }

        // --- Ergebnisse abgeschlossener Vorphasen ---
        $previousResults = rescue(fn () => PhaseAgentResult::where('projekt_id', $this->projekt->id)
            ->where('phase_nr', '<', $this->phaseNr)
            ->where('status', 'completed')
            ->whereNotNull('content')
            ->orderBy('phase_nr')
            ->orderByDesc('created_at')
            ->get()
            ->unique('phase_nr'), collect());

        if ($previousResults->isNotEmpty()) {
            $lines[] = '';
            $lines[] = "=== ERGEBNISSE VORHERIGER PHASEN ===";
            foreach ($previousResults as $result) {
                $lines[] = "--- Phase {$result->phase_nr} ---";
                $lines[] = mb_substr((string) $result->content, 0, 800);
            }
        }

        // --- Verfügbare Dokumente ---
        try {
            $paperCount = (int) DB::selectOne(
                'SELECT COUNT(*) AS cnt FROM paper_embeddings WHERE projekt_id = ?::uuid',
                [$this->projekt->id]
            )?->cnt;
        } catch (\Throwable) {
            $paperCount = 0;
        }

        $trefferCount = $this->projekt->p5Treffer()->count();

        if ($paperCount > 0 || $trefferCount > 0) {
            $lines[] = '';
            $lines[] = "=== VORHANDENE DOKUMENTE ===";
            if ($paperCount > 0) {
                $lines[] = "Indexierte Dokument-Abschnitte (paper_embeddings): {$paperCount}";
            }
            if ($trefferCount > 0) {
                $lines[] = "Importierte Treffer (p5_treffer): {$trefferCount}";
            }
        }

        $lines[] = '';
        $lines[] = "Aktuelle Phase: P{$this->phaseNr}";
        $lines[] = "Bitte nutze die oben genannte Projekt-ID für alle DB-Operationen (SET LOCAL app.current_projekt_id).";

        return [
            ['role' => 'user', 'content' => implode("\n", $lines)],
        ];
    }
}; ?>

<div wire:poll.3s="pollForResult">
    <button
        wire:click="runAgent"
        wire:loading.attr="disabled"
        wire:target="runAgent"
        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
    >
        <span wire:loading.remove wire:target="runAgent">{{ $label }}</span>
        <span wire:loading wire:target="runAgent" class="inline-flex items-center gap-1">
            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            {{ __('KI arbeitet…') }}
        </span>
    </button>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-2xl dark:bg-neutral-800">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4 dark:border-neutral-700">
                    <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ $label }} — {{ __('Ergebnis') }}
                    </h3>
                    <button wire:click="dismissResult" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="max-h-[60vh] overflow-y-auto px-6 py-4">
                    @if ($this->loadingPhase)
                        <div class="flex items-center justify-center py-8">
                            <div class="flex flex-col items-center gap-3">
                                <svg class="h-8 w-8 animate-spin text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('KI arbeitet daran…') }}</p>
                            </div>
                        </div>
                    @else
                        @php
                            $result = $this->latestResult();
                        @endphp
                        @if ($result && $result->status === 'failed')
                            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                                <p class="mb-2 text-sm font-medium text-red-700 dark:text-red-400">{{ __('Fehler') }}</p>
                                <p class="text-sm text-red-700 dark:text-red-400">{{ $result->error_message }}</p>
                            </div>
                        @elseif ($result && $result->status === 'completed' && $result->content)
                            <div class="prose prose-sm max-w-none dark:prose-invert">
                                {!! Illuminate\Support\Str::markdown($result->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Footer --}}
                @if (! $this->loadingPhase)
                    @php
                        $result = $this->latestResult();
                    @endphp
                    <div class="flex justify-end gap-3 border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                        <button wire:click="dismissResult" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">
                            {{ __('Verwerfen') }}
                        </button>
                        @if ($result && $result->status === 'completed' && $result->content)
                            <button wire:click="acceptResult" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                {{ __('Übernehmen') }}
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
