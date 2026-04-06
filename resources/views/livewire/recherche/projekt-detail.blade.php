<?php

use App\Models\Recherche\Projekt;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;

    #[Url(as: 'tab')]
    public int $activeTab = 1;

    public function mount(Projekt $projekt): void
    {
        $this->authorize('view', $projekt);
        $this->projekt = $projekt->load('phasen');
    }

    public function switchTab(int $tab): void
    {
        $this->activeTab = max(1, min(8, $tab));
    }

    public function getPhaseStatus(int $nr): ?string
    {
        return $this->projekt->phasen->firstWhere('phase_nr', $nr)?->status;
    }
}; ?>

<section class="space-y-6">
    {{-- Header --}}
    <div>
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ $projekt->titel }}
                </h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ __('Erstellt') }}: {{ $projekt->erstellt_am?->format('d.m.Y H:i') }}
                    @if ($projekt->review_typ)
                        &middot; {{ $projekt->review_typ }}
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('recherche.mayring', $projekt) }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                    Mayring-Codierung
                </a>
                <a href="{{ route('recherche') }}" wire:navigate
                   class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                    &larr; {{ __('Zurück') }}
                </a>
            </div>
        </div>

        @if ($projekt->forschungsfrage)
            <div class="mt-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <h3 class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Forschungsfrage') }}</h3>
                <p class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $projekt->forschungsfrage }}</p>
            </div>
        @endif

        {{-- Progress Bar --}}
        @php
            $completedCount = $projekt->phasen->where('status', 'abgeschlossen')->count();
            $progressPercent = (int) round($completedCount / 8 * 100);
        @endphp
        <div class="mt-4">
            <div class="flex items-center justify-between mb-1 text-xs text-zinc-500 dark:text-zinc-400">
                <span>{{ __('Fortschritt') }}</span>
                <span>{{ $completedCount }}/8 {{ __('Phasen abgeschlossen') }}</span>
            </div>
            <div class="w-full rounded-full bg-zinc-200 dark:bg-zinc-700 h-2">
                <div
                    class="h-2 rounded-full bg-blue-500 dark:bg-blue-400 transition-all duration-500"
                    style="width: {{ $progressPercent }}%"
                ></div>
            </div>
        </div>
    </div>

    {{-- Tab Bar --}}
    <div class="border-b border-zinc-200 dark:border-zinc-700">
        <nav class="-mb-px flex space-x-1 overflow-x-auto">
            @php
                $tabLabels = [
                    1 => 'Strukturierung',
                    2 => 'Review-Typ',
                    3 => 'Quellen',
                    4 => 'Suchstrings',
                    5 => 'Screening',
                    6 => 'Qualität',
                    7 => 'Synthese',
                    8 => 'Dokumentation',
                ];
            @endphp
            @for ($i = 1; $i <= 8; $i++)
                @php $status = $this->getPhaseStatus($i); @endphp
                <button
                    wire:click="switchTab({{ $i }})"
                    @class([
                        'whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition-colors',
                        'border-blue-500 text-blue-600 dark:border-blue-400 dark:text-blue-400' => $activeTab === $i,
                        'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' => $activeTab !== $i,
                    ])
                >
                    <span class="flex items-center gap-1.5">
                        P{{ $i }}
                        <span class="hidden sm:inline">{{ $tabLabels[$i] }}</span>
                        @if ($status === 'abgeschlossen')
                            <span class="h-2 w-2 rounded-full bg-green-500"></span>
                        @elseif ($status === 'in_bearbeitung')
                            <span class="h-2 w-2 rounded-full bg-yellow-500"></span>
                        @endif
                    </span>
                </button>
            @endfor
        </nav>
    </div>

    {{-- Group Status Header & Agent Controls --}}
    <livewire:recherche.phase-group-status-header :projekt="$projekt" :key="'gsh-'.$projekt->id" />

    {{-- Phase Content --}}
    <div>
        @if ($activeTab === 1)
            <livewire:recherche.phase-p1 :projekt="$projekt" :key="'p1-'.$projekt->id" />
        @elseif ($activeTab === 2)
            <livewire:recherche.phase-p2 :projekt="$projekt" :key="'p2-'.$projekt->id" />
        @elseif ($activeTab === 3)
            <livewire:recherche.phase-p3 :projekt="$projekt" :key="'p3-'.$projekt->id" />
        @elseif ($activeTab === 4)
            <livewire:recherche.phase-p4 :projekt="$projekt" :key="'p4-'.$projekt->id" />
        @elseif ($activeTab === 5)
            <livewire:recherche.phase-p5 :projekt="$projekt" :key="'p5-'.$projekt->id" />
        @elseif ($activeTab === 6)
            <livewire:recherche.phase-p6 :projekt="$projekt" :key="'p6-'.$projekt->id" />
        @elseif ($activeTab === 7)
            <livewire:recherche.phase-p7 :projekt="$projekt" :key="'p7-'.$projekt->id" />
        @elseif ($activeTab === 8)
            <livewire:recherche.phase-p8 :projekt="$projekt" :key="'p8-'.$projekt->id" />
        @endif
    </div>
</section>
