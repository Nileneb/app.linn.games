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
                <h2 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ $projekt->titel }}
                </h2>
                <p class="mt-1 text-sm text-neutral-500">
                    {{ __('Erstellt') }}: {{ $projekt->erstellt_am?->format('d.m.Y H:i') }}
                    @if ($projekt->review_typ)
                        &middot; {{ $projekt->review_typ }}
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('recherche.mayring', $projekt) }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700">
                    Mayring-Codierung
                </a>
                <a href="{{ route('recherche') }}" wire:navigate
                   class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                    &larr; {{ __('Zurück') }}
                </a>
            </div>
        </div>

        @if ($projekt->forschungsfrage)
            <div class="mt-3 rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800/50">
                <h3 class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">{{ __('Forschungsfrage') }}</h3>
                <p class="mt-1 text-neutral-900 dark:text-neutral-100">{{ $projekt->forschungsfrage }}</p>
            </div>
        @endif
    </div>

    {{-- Tab Bar --}}
    <div class="border-b border-neutral-200 dark:border-neutral-700">
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
                        'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-300' => $activeTab !== $i,
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
