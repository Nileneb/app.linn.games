<?php

use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;

    public function mount(Projekt $projekt): void
    {
        abort_unless($projekt->user_id === Auth::id(), 403);

        $this->projekt = $projekt->load([
            'phasen',
            'p5Treffer' => fn ($q) => $q->where('ist_duplikat', false)->orderBy('record_id'),
        ]);
    }
}; ?>

<section class="space-y-6">
    {{-- Header --}}
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

    {{-- Forschungsfrage --}}
    @if ($projekt->forschungsfrage)
        <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
            <h3 class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Forschungsfrage') }}</h3>
            <p class="mt-1 text-neutral-900 dark:text-neutral-100">{{ $projekt->forschungsfrage }}</p>
        </div>
    @endif

    {{-- Phasen --}}
    @if ($projekt->phasen->isNotEmpty())
        <div>
            <h3 class="mb-2 text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Phasen') }}</h3>
            <div class="space-y-2">
                @foreach ($projekt->phasen->sortBy('phase_nr') as $phase)
                    <div class="flex items-center justify-between rounded-lg border border-neutral-200 px-4 py-2 dark:border-neutral-700">
                        <span class="text-neutral-900 dark:text-neutral-100">
                            P{{ $phase->phase_nr }}: {{ $phase->titel }}
                        </span>
                        <span @class([
                            'rounded px-2 py-0.5 text-xs',
                            'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $phase->status === 'abgeschlossen',
                            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' => $phase->status === 'in_bearbeitung',
                            'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300' => $phase->status === 'offen' || !$phase->status,
                        ])>
                            {{ $phase->status ?? 'offen' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Treffer (read-only) --}}
    @if ($projekt->p5Treffer->isNotEmpty())
        <div>
            <h3 class="mb-2 text-sm font-medium text-neutral-600 dark:text-neutral-400">
                {{ __('Treffer') }} ({{ $projekt->p5Treffer->count() }})
            </h3>
            <div class="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-700">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">{{ __('Titel') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">{{ __('Autoren') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">{{ __('Jahr') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">{{ __('Quelle') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($projekt->p5Treffer as $treffer)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-600 dark:text-neutral-300">{{ $treffer->record_id }}</td>
                                <td class="px-4 py-2 text-sm text-neutral-900 dark:text-neutral-100">{{ str()->limit($treffer->titel, 80) }}</td>
                                <td class="px-4 py-2 text-sm text-neutral-600 dark:text-neutral-300">{{ str()->limit($treffer->autoren, 50) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-600 dark:text-neutral-300">{{ $treffer->jahr }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-600 dark:text-neutral-300">{{ $treffer->datenbank_quelle }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Back link --}}
    <div>
        <a href="{{ route('recherche') }}" wire:navigate class="text-sm text-blue-600 hover:underline dark:text-blue-400">
            &larr; {{ __('Zurück zur Übersicht') }}
        </a>
    </div>
</section>
