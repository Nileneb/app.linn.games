<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
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
        $lines = [];

        // --- Projekt-Identifikation (explizit im User-Message, nicht nur im System-Context) ---
        $lines[] = "=== PROJEKTKONTEXT ===";
        $lines[] = "Projekt-ID: {$this->projekt->id}";
        $lines[] = "Forschungsfrage: {$this->projekt->forschungsfrage}";

        if ($this->projekt->review_typ) {
            $lines[] = "Review-Typ: {$this->projekt->review_typ}";
        }

        // --- Ergebnisse abgeschlossener Vorphasen ---
        $previousResults = rescue(
            fn () => PhaseAgentResult::where('projekt_id', $this->projekt->id)
                ->where('phase_nr', '<', $this->phaseNr)
                ->where('status', 'completed')
                ->whereNotNull('content')
                ->orderBy('phase_nr')
                ->orderByDesc('created_at')
                ->get()
                ->unique('phase_nr'),
            collect(),
            report: true,
        );

        if ($previousResults->isNotEmpty()) {
            $lines[] = '';
            $lines[] = "=== ERGEBNISSE VORHERIGER PHASEN ===";
            foreach ($previousResults as $result) {
                $lines[] = "--- Phase {$result->phase_nr} ---";
                $lines[] = mb_substr((string) $result->content, 0, 800);
            }
        }

        // --- Verfügbare Dokumente ---
        $paperCount = rescue(
            fn () => (int) DB::selectOne(
                'SELECT COUNT(*) AS cnt FROM paper_embeddings WHERE projekt_id = ?::uuid',
                [$this->projekt->id]
            )?->cnt,
            0,
            report: true,
        );

        $trefferCount = rescue(
            fn () => $this->projekt->p5Treffer()->count(),
            0,
            report: true,
        );

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
