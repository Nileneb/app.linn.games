<?php

use App\Models\PhaseAgentResult;
use App\Models\Recherche\{Projekt, Phase};
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;
    public int $currentPhaseNr = 1;
    public bool $agentRunning = false;
    public ?int $runningGroupNumber = null;
    public bool $pipelineStarted = false;

    private function getRunningGroups(): array
    {
        if (!$this->pipelineStarted) {
            return [];
        }

        $running = [];
        $groupPhases = [
            1 => [1, 3],
            2 => [4, 6],
            3 => [7, 8],
        ];

        foreach ($groupPhases as $groupNum => $phases) {
            $hasPending = PhaseAgentResult::where('projekt_id', $this->projekt->id)
                ->whereBetween('phase_nr', $phases[0], $phases[1])
                ->where('status', 'pending')
                ->exists();

            if ($hasPending) {
                $running[] = $groupNum;
            }
        }

        return $running;
    }

    protected $listeners = ['refreshAgentStatus' => 'checkAgentStatus'];

    public function mount(): void
    {
        // Check if there's an active pending job
        $pendingResult = PhaseAgentResult::where('projekt_id', $this->projekt->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->first();

        if ($pendingResult) {
            $this->agentRunning = true;
            $this->runningGroupNumber = match(true) {
                in_array($pendingResult->phase_nr, [1, 2, 3]) => 1,
                in_array($pendingResult->phase_nr, [4, 5, 6]) => 2,
                in_array($pendingResult->phase_nr, [7, 8])    => 3,
                default => null,
            };
        }

        $this->checkAgentStatus();
    }

    public function startFullPipeline(): void
    {
        $this->pipelineStarted = true;
        $this->startGroupAgent(1);
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
        if (!$this->pipelineStarted) {
            return;
        }

        // Get the first phase of each group
        $groupPhases = [
            1 => [1, 3],
            2 => [4, 6],
            3 => [7, 8],
        ];

        // Check group 1 completion (always runs first)
        $group1Completed = PhaseAgentResult::where('projekt_id', $this->projekt->id)
            ->whereBetween('phase_nr', [$groupPhases[1][0], $groupPhases[1][1]])
            ->where('status', '!=', 'pending')
            ->count() === 3;

        // Check group 2 progress (start group 3 at 30% of group 2)
        $group2Results = PhaseAgentResult::where('projekt_id', $this->projekt->id)
            ->whereBetween('phase_nr', [$groupPhases[2][0], $groupPhases[2][1]])
            ->where('status', '!=', 'pending')
            ->count();

        $group2Progress = $group2Results / 3; // 0 to 1

        // Check if group 3 should start (at 30% of group 2 OR if group 2 is done)
        $shouldStartGroup3 = $group2Progress >= 0.3;

        // Auto-start groups based on triggers
        if ($this->pipelineStarted) {
            // Group 1 already started in startFullPipeline()

            // Group 2: Start when Group 1 is done
            if ($group1Completed && !PhaseAgentResult::where('projekt_id', $this->projekt->id)
                ->whereBetween('phase_nr', [$groupPhases[2][0], $groupPhases[2][1]])
                ->exists()) {
                $this->startGroupAgent(2);
            }

            // Group 3: Start when Group 2 reaches 30% progress
            if ($shouldStartGroup3 && !PhaseAgentResult::where('projekt_id', $this->projekt->id)
                ->whereBetween('phase_nr', [$groupPhases[3][0], $groupPhases[3][1]])
                ->exists()) {
                $this->startGroupAgent(3);
            }

            // Check if all groups are done
            $allGroupsDone = collect([1, 2, 3])->every(function ($groupNum) use ($groupPhases) {
                $phases = $groupPhases[$groupNum];
                $completed = PhaseAgentResult::where('projekt_id', $this->projekt->id)
                    ->whereBetween('phase_nr', [$phases[0], $phases[1]])
                    ->where('status', '!=', 'pending')
                    ->count();
                $total = $phases[1] - $phases[0] + 1;
                return $completed === $total;
            });

            if ($allGroupsDone) {
                $this->agentRunning = false;
                $this->runningGroupNumber = null;
                $this->pipelineStarted = false;
            }
        }
    }

    public function with(): array
    {
        return [
            'phases' => $this->projekt->phasen()->orderBy('phase_nr')->get(),
        ];
    }
}; ?>

<div wire:poll.5s="checkAgentStatus" class="mb-8">
    {{-- Start Button + Progress Tracker --}}
    <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Analyse-Fortschritt</h3>
            @php
                $completedCount = $phases->where('status', 'abgeschlossen')->count();
                $progressPercent = (int)round($completedCount / 8 * 100);
            @endphp
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $completedCount }}/8 {{ __('Phasen') }}</span>
        </div>

        {{-- Progress Bar (All 8 Phases) --}}
        <div class="mb-4">
            <div class="flex gap-1">
                @for ($i = 1; $i <= 8; $i++)
                    @php($phase = $phases->firstWhere('phase_nr', $i))
                    <div class="flex-1 group">
                        <div class="relative h-8 rounded-md overflow-hidden border border-zinc-200 dark:border-zinc-700 transition-all duration-300
                            @if ($phase?->status === 'abgeschlossen') bg-green-100 dark:bg-green-900/30
                            @elseif ($phase?->status === 'in_bearbeitung') bg-amber-100 dark:bg-amber-900/30
                            @else bg-zinc-100 dark:bg-zinc-800
                            @endif
                        ">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">P{{ $i }}</span>
                            </div>
                            @if ($phase?->status === 'abgeschlossen')
                                <div class="absolute inset-0 bg-gradient-to-r from-green-400 to-green-500 opacity-40"></div>
                            @elseif ($phase?->status === 'in_bearbeitung')
                                <div class="absolute inset-0 bg-gradient-to-r from-amber-400 to-amber-500 opacity-40 animate-pulse"></div>
                            @endif
                        </div>
                        <div class="text-center mt-1">
                            <span class="text-2xs text-zinc-400 dark:text-zinc-500">
                                @if ($phase?->status === 'abgeschlossen') ✓ @elseif ($phase?->status === 'in_bearbeitung') ⟳ @else — @endif
                            </span>
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Group Labels --}}
        <div class="flex gap-1 mb-6 text-2xs font-medium text-zinc-500 dark:text-zinc-400">
            <div class="flex-1 text-center">Scoping</div>
            <div class="flex-1 text-center">Recherche</div>
            <div class="flex-1 text-center">Synthese</div>
        </div>

        {{-- Start / Status Display --}}
        @php($runningGroups = $this->getRunningGroups())
        @if ($pipelineStarted && !empty($runningGroups))
            <div class="flex items-center gap-3 rounded-lg bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 px-6 py-3 dark:from-amber-900/20 dark:to-orange-900/20 dark:border-amber-800">
                <svg class="h-5 w-5 animate-spin text-amber-600 dark:text-amber-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Pipeline läuft…</p>
                    <p class="text-xs text-amber-700 dark:text-amber-300">
                        @php
                            $groupNames = ['', 'Scoping', 'Recherche', 'Synthese'];
                            $activeNames = array_map(fn($g) => $groupNames[$g], $runningGroups);
                        @endphp
                        Aktiv: <strong>{{ implode(', ', $activeNames) }}</strong>
                    </p>
                </div>
            </div>
        @else
            <button
                wire:click="startFullPipeline"
                class="w-full group relative rounded-lg bg-gradient-to-r from-zinc-900 via-zinc-800 to-zinc-900 px-6 py-3 text-center font-semibold text-white shadow-lg transition-all duration-300 hover:shadow-2xl hover:shadow-zinc-900/50 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900"
            >
                <span class="flex items-center justify-center gap-2">
                    <span class="text-base">▶</span>
                    <span>Komplette Analyse starten</span>
                    <span class="text-base group-hover:translate-x-0.5 transition-transform">→</span>
                </span>
            </button>
        @endif
    </div>
</div>
