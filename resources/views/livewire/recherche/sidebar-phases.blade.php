<?php

use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Services\PhaseCountService;
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;

    public function with(): array
    {
        $results = PhaseAgentResult::where('projekt_id', $this->projekt->id)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('phase_nr')
            ->map(fn ($g) => $g->first());

        $anyPending = $results->contains(fn ($r) => $r->status === 'pending');
        $countService = app(PhaseCountService::class);

        $phases = [];
        foreach (range(1, 8) as $i) {
            $status = $results->get($i)?->status;
            $hasData = $countService->countByPhase($this->projekt, $i) > 0;

            $phases[$i] = [
                'status' => $status,
                'hasData' => $hasData,
                'state' => match (true) {
                    $status === 'pending' => 'running',
                    $status === 'completed' && $hasData => 'done',
                    $status === 'completed' && ! $hasData => 'empty',
                    $status === 'failed' => 'failed',
                    default => 'idle',
                },
            ];
        }

        return [
            'phases' => $phases,
            'anyPending' => $anyPending,
        ];
    }
}; ?>

<nav wire:poll.5s class="space-y-0.5">
    @php
        $labels = [
            1 => 'Strukturierung',
            2 => 'Review-Typ',
            3 => 'Quellen',
            4 => 'Suchstrings',
            5 => 'Screening',
            6 => 'Qualitat',
            7 => 'Synthese',
            8 => 'Dokumentation',
        ];
        $stateStyles = [
            'idle'    => 'border-l-zinc-300 dark:border-l-zinc-600 text-zinc-500 dark:text-zinc-400',
            'running' => 'border-l-amber-400 dark:border-l-amber-500 text-amber-700 dark:text-amber-300 bg-amber-50/50 dark:bg-amber-900/10',
            'done'    => 'border-l-green-500 dark:border-l-green-400 text-green-700 dark:text-green-300',
            'empty'   => 'border-l-red-400 dark:border-l-red-500 text-red-600 dark:text-red-400',
            'failed'  => 'border-l-red-500 dark:border-l-red-400 text-red-700 dark:text-red-300',
        ];
    @endphp

    @foreach ($phases as $nr => $phase)
        <button
            wire:click="$dispatch('sidebar-switch-tab', { tab: {{ $nr }} })"
            @disabled($anyPending)
            @class([
                'flex w-full items-center justify-between rounded-r-md border-l-[3px] px-3 py-1.5 text-left text-sm transition-all',
                'hover:bg-zinc-100 dark:hover:bg-zinc-700/30' => ! $anyPending,
                'cursor-not-allowed opacity-60' => $anyPending && $phase['state'] !== 'running',
                $stateStyles[$phase['state']],
            ])
        >
            <span class="flex items-center gap-2">
                <span class="font-mono text-xs font-bold">P{{ $nr }}</span>
                <span class="truncate text-xs">{{ $labels[$nr] }}</span>
            </span>
            <span class="flex-shrink-0">
                @if ($phase['state'] === 'running')
                    <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                @elseif ($phase['state'] === 'done')
                    <svg class="h-3.5 w-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                @elseif ($phase['state'] === 'failed' || $phase['state'] === 'empty')
                    <svg class="h-3.5 w-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                    </svg>
                @else
                    <span class="h-2 w-2 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                @endif
            </span>
        </button>
    @endforeach
</nav>
