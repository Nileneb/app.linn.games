<?php

use App\Models\PhaseAgentResult;
use App\Models\Recherche\{Projekt, Phase};
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;
    public int $currentPhaseNr = 1;
    public bool $agentRunning = false;
    public ?int $runningGroupNumber = null;

    protected $listeners = ['refreshAgentStatus' => 'checkAgentStatus'];

    public function mount(): void
    {
        $this->checkAgentStatus();
    }

    public function startGroupAgent(int $groupNumber): void
    {
        $this->runningGroupNumber = $groupNumber;
        $this->agentRunning = true;

        // Determine phase number and agent config for this group
        $phaseNr = match ($groupNumber) {
            1 => 1,  // Start with P1 for group 1
            2 => 4,  // Start with P4 for group 2
            3 => 7,  // Start with P7 for group 3
        };

        $agentConfigKey = match ($groupNumber) {
            1 => 'scoping_mapping_agent',
            2 => 'search_agent',
            3 => 'review_agent',
        };

        // Dispatch the agent job
        \App\Jobs\ProcessPhaseAgentJob::dispatch(
            projektId: $this->projekt->id,
            phaseNr: $phaseNr,
            agentConfigKey: $agentConfigKey,
            messages: [],
            context: []
        );

        // Start polling for completion
        $this->dispatch('startPollingAgentStatus');
    }

    public function checkAgentStatus(): void
    {
        if (!$this->runningGroupNumber) {
            return;
        }

        $phaseNr = match ($this->runningGroupNumber) {
            1 => 1,
            2 => 4,
            3 => 7,
        };

        // Check if agent job completed
        $latestResult = PhaseAgentResult::where('projekt_id', $this->projekt->id)
            ->where('phase_nr', $phaseNr)
            ->orderByDesc('created_at')
            ->first();

        if ($latestResult && $latestResult->status !== 'pending') {
            $this->agentRunning = false;
            $this->runningGroupNumber = null;
        }
    }

    public function with(): array
    {
        return [
            'phases' => $this->projekt->phasen()->orderBy('phase_nr')->get(),
        ];
    }
}; ?>

<div class="mb-6 space-y-4" wire:poll.5s="checkAgentStatus">
    {{-- ═══ Gruppe 1: P1-P3 Scoping ═══ --}}
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-3 grid grid-cols-3 gap-4">
            @foreach ([1, 2, 3] as $phaseNum)
                @php
                    $phase = $phases->firstWhere('phase_nr', $phaseNum);
                    $statusIcon = match ($phase?->status) {
                        'abgeschlossen' => '✓',
                        'in_bearbeitung' => '●',
                        default => '○',
                    };
                    $statusColor = match ($phase?->status) {
                        'abgeschlossen' => 'text-green-600 dark:text-green-400',
                        'in_bearbeitung' => 'text-yellow-500 dark:text-yellow-400 animate-pulse',
                        default => 'text-slate-400 dark:text-slate-500',
                    };
                @endphp
                <div class="flex items-start gap-2 text-sm">
                    <span class="mt-0.5 font-bold {{ $statusColor }}">{{ $statusIcon }}</span>
                    <div>
                        <div class="font-semibold text-slate-900 dark:text-slate-100">P{{ $phaseNum }}</div>
                        <div class="text-xs text-slate-600 dark:text-slate-400">
                            @php
                                echo match ($phaseNum) {
                                    1 => 'Strukturmodell',
                                    2 => 'Mapping Cluster',
                                    3 => 'Extraktion',
                                };
                            @endphp
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <button
            wire:click="startGroupAgent(1)"
            @disabled($agentRunning && $runningGroupNumber === 1)
            class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-indigo-700 dark:hover:bg-indigo-600"
        >
            @if ($agentRunning && $runningGroupNumber === 1)
                <span class="inline-block animate-spin mr-2">⟳</span> KI läuft…
            @else
                🎯 START SCOPING
            @endif
        </button>
    </div>

    {{-- ═══ Gruppe 2: P4-P6 Search & Screening ═══ --}}
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-3 grid grid-cols-3 gap-4">
            @foreach ([4, 5, 6] as $phaseNum)
                @php
                    $phase = $phases->firstWhere('phase_nr', $phaseNum);
                    $statusIcon = match ($phase?->status) {
                        'abgeschlossen' => '✓',
                        'in_bearbeitung' => '●',
                        default => '○',
                    };
                    $statusColor = match ($phase?->status) {
                        'abgeschlossen' => 'text-green-600 dark:text-green-400',
                        'in_bearbeitung' => 'text-yellow-500 dark:text-yellow-400 animate-pulse',
                        default => 'text-slate-400 dark:text-slate-500',
                    };
                @endphp
                <div class="flex items-start gap-2 text-sm">
                    <span class="mt-0.5 font-bold {{ $statusColor }}">{{ $statusIcon }}</span>
                    <div>
                        <div class="font-semibold text-slate-900 dark:text-slate-100">P{{ $phaseNum }}</div>
                        <div class="text-xs text-slate-600 dark:text-slate-400">
                            @php
                                echo match ($phaseNum) {
                                    4 => 'Suchanfrage',
                                    5 => 'Screening',
                                    6 => 'Coding',
                                };
                            @endphp
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <button
            wire:click="startGroupAgent(2)"
            @disabled($agentRunning && $runningGroupNumber === 2)
            class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-indigo-700 dark:hover:bg-indigo-600"
        >
            @if ($agentRunning && $runningGroupNumber === 2)
                <span class="inline-block animate-spin mr-2">⟳</span> KI läuft…
            @else
                🔍 START SEARCH
            @endif
        </button>
    </div>

    {{-- ═══ Gruppe 3: P7-P8 Synthese & Report ═══ --}}
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-3 grid grid-cols-3 gap-4">
            @foreach ([7, 8] as $phaseNum)
                @php
                    $phase = $phases->firstWhere('phase_nr', $phaseNum);
                    $statusIcon = match ($phase?->status) {
                        'abgeschlossen' => '✓',
                        'in_bearbeitung' => '●',
                        default => '○',
                    };
                    $statusColor = match ($phase?->status) {
                        'abgeschlossen' => 'text-green-600 dark:text-green-400',
                        'in_bearbeitung' => 'text-yellow-500 dark:text-yellow-400 animate-pulse',
                        default => 'text-slate-400 dark:text-slate-500',
                    };
                @endphp
                <div class="flex items-start gap-2 text-sm">
                    <span class="mt-0.5 font-bold {{ $statusColor }}">{{ $statusIcon }}</span>
                    <div>
                        <div class="font-semibold text-slate-900 dark:text-slate-100">P{{ $phaseNum }}</div>
                        <div class="text-xs text-slate-600 dark:text-slate-400">
                            @php
                                echo match ($phaseNum) {
                                    7 => 'Synthese',
                                    8 => 'Bericht',
                                };
                            @endphp
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <button
            wire:click="startGroupAgent(3)"
            @disabled($agentRunning && $runningGroupNumber === 3)
            class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-indigo-700 dark:hover:bg-indigo-600"
        >
            @if ($agentRunning && $runningGroupNumber === 3)
                <span class="inline-block animate-spin mr-2">⟳</span> KI läuft…
            @else
                📊 START SYNTHESE
            @endif
        </button>
    </div>
</div>
