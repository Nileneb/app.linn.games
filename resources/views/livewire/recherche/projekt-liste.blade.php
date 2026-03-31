<?php

use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public function projekte()
    {
        return Projekt::where('user_id', Auth::id())
            ->orderByDesc('erstellt_am')
            ->get();
    }

    public function with(): array
    {
        return [
            'projekte' => $this->projekte(),
        ];
    }
}; ?>

<section>
    <div class="space-y-4">
        @forelse ($projekte as $projekt)
            <a href="{{ route('recherche.projekt', $projekt) }}" wire:navigate
               class="block rounded-lg border border-neutral-200 p-4 transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-neutral-900 dark:text-neutral-100">
                        {{ str()->limit($projekt->titel, 80) }}
                    </h3>
                    <span class="text-xs text-neutral-500">
                        {{ $projekt->erstellt_am?->diffForHumans() }}
                    </span>
                </div>
                @if ($projekt->review_typ)
                    <span class="mt-1 inline-block rounded bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
                        {{ $projekt->review_typ }}
                    </span>
                @endif
            </a>
        @empty
            <p class="text-neutral-500 dark:text-neutral-400">
                {{ __('Noch keine Recherche-Projekte vorhanden.') }}
            </p>
        @endforelse
    </div>
</section>
