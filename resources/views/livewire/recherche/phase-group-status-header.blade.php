<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Services\AgentPromptBuilder;
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;

    public function startPipeline(int $fromPhase): void
    {
        $config = config('phase_chain');
        if (! isset($config[$fromPhase])) {
            $this->dispatch('notify', type: 'error', message: 'Phase nicht konfiguriert');
            return;
        }

        $configKey = $config[$fromPhase]['agent_config_key'];
        $promptBuilder = app(AgentPromptBuilder::class);

        $messages = [
            ['role' => 'system', 'content' => $promptBuilder->buildSystemPrompt($this->projekt, $fromPhase, $configKey)],
            ['role' => 'user',   'content' => $promptBuilder->buildUserPrompt($this->projekt, $fromPhase)],
        ];

        $context = [
            'source'       => 'pipeline_trigger',
            'projekt_id'   => $this->projekt->id,
            'workspace_id' => $this->projekt->workspace_id,
            'phase_nr'     => $fromPhase,
            'user_id'      => auth()->id(),
            'user_name'    => auth()->user()?->name,
        ];

        PhaseAgentResult::create([
            'projekt_id'       => $this->projekt->id,
            'user_id'          => auth()->id(),
            'phase_nr'         => $fromPhase,
            'agent_config_key' => $configKey,
            'status'           => 'pending',
        ]);

        ProcessPhaseAgentJob::dispatch(
            $this->projekt->id,
            $fromPhase,
            $configKey,
            $messages,
            $context,
        );
    }

    public function with(): array
    {
        // Latest PhaseAgentResult per phase (null if never run)
        $results = PhaseAgentResult::where('projekt_id', $this->projekt->id)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('phase_nr')
            ->map(fn ($g) => $g->first());

        $anyPending     = $results->contains(fn ($r) => $r->status === 'pending');
        $p4Completed    = $results->get(4)?->status === 'completed';
        $seg1Started    = $results->has(1);
        $seg2Started    = $results->has(5);
        $completedCount = $this->projekt->phasen()->where('status', 'abgeschlossen')->count();

        return [
            'results'        => $results,           // keyed by phase_nr
            'anyPending'     => $anyPending,
            'p4Completed'    => $p4Completed,
            'seg1Started'    => $seg1Started,
            'seg2Started'    => $seg2Started,
            'completedCount' => $completedCount,
        ];
    }
}; ?>

<div wire:poll.5s class="mb-8">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">

        {{-- Header --}}
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Analyse-Fortschritt</h3>
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $completedCount }}/8 Phasen</span>
        </div>

        {{-- Per-Phase Status Bar --}}
        <div class="mb-6 flex gap-1">
            @foreach (range(1, 8) as $i)
                @php
                    $r      = $results->get($i);
                    $status = $r?->status;         // pending | completed | failed | null
                @endphp
                <div class="flex-1">
                    <div @class([
                        'relative h-8 rounded-md overflow-hidden border transition-all duration-300',
                        'border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800'         => $status === null,
                        'border-amber-300 bg-amber-100 dark:border-amber-700 dark:bg-amber-900/30'  => $status === 'pending',
                        'border-green-300 bg-green-100 dark:border-green-700 dark:bg-green-900/30'  => $status === 'completed',
                        'border-red-300 bg-red-100 dark:border-red-700 dark:bg-red-900/30'          => $status === 'failed',
                    ])>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">P{{ $i }}</span>
                        </div>
                        @if ($status === 'pending')
                            <div class="absolute inset-0 bg-gradient-to-r from-amber-400 to-amber-500 opacity-30 animate-pulse"></div>
                        @elseif ($status === 'completed')
                            <div class="absolute inset-0 bg-gradient-to-r from-green-400 to-green-500 opacity-30"></div>
                        @elseif ($status === 'failed')
                            <div class="absolute inset-0 bg-gradient-to-r from-red-400 to-red-500 opacity-30"></div>
                        @endif
                    </div>
                    <div class="mt-1 text-center text-xs text-zinc-400 dark:text-zinc-500">
                        @if ($status === 'pending') ⟳
                        @elseif ($status === 'completed') ✓
                        @elseif ($status === 'failed') ✗
                        @else —
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Segment Labels --}}
        <div class="mb-6 grid grid-cols-2 gap-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500">
            <div>P1–P4 · Scoping & Suche</div>
            <div>P5–P8 · Screening & Synthese</div>
        </div>

        {{-- Action Area --}}
        <div class="space-y-3">
            {{-- Segment 1: P1–P4 --}}
            @if ($anyPending)
                <div class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-900/20">
                    <svg class="h-4 w-4 animate-spin text-amber-600 dark:text-amber-400 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span class="text-sm font-medium text-amber-800 dark:text-amber-200">KI läuft im Hintergrund…</span>
                </div>
            @elseif (! $seg1Started)
                <button
                    wire:click="startPipeline(1)"
                    class="w-full rounded-lg bg-zinc-900 px-4 py-3 text-sm font-semibold text-white shadow transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300"
                >
                    ▶ Analyse P1–P4 starten
                </button>
            @elseif (! $p4Completed)
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-center text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                    P1–P4 laufen durch · PhaseChain übernimmt automatisch
                </div>
            @endif

            {{-- Segment 2: P5–P8 (after P4 done, manual paper import required) --}}
            @if ($p4Completed && ! $seg2Started && ! $anyPending)
                <button
                    wire:click="startPipeline(5)"
                    class="w-full rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-800 shadow transition hover:bg-indigo-100 dark:border-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200 dark:hover:bg-indigo-900/50"
                >
                    ▶ Analyse P5–P8 starten
                    <span class="block text-xs font-normal text-indigo-600 dark:text-indigo-400">(Nach Paper-Import)</span>
                </button>
            @endif
        </div>

    </div>
</div>
