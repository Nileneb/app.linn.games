<?php

use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public function projekte()
    {
        $workspaceId = Auth::user()?->activeWorkspaceId();

        if ($workspaceId === null) {
            return collect();
        }

        return Projekt::where('workspace_id', $workspaceId)
            ->orderByDesc('erstellt_am')
            ->get();
    }

    public function deleteProjekt(string $id): void
    {
        $workspaceId = Auth::user()?->activeWorkspaceId();

        abort_unless($workspaceId !== null, 403);

        $projekt = Projekt::where('workspace_id', $workspaceId)->findOrFail($id);
        $this->authorize('delete', $projekt);
        $projekt->delete();
    }

    public function with(): array
    {
        return [
            'projekte' => $this->projekte(),
        ];
    }
}; ?>

<section>
    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Meine Recherche-Projekte') }}</h1>
            <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">{{ __('Systematische Literaturrecherchen verwalten') }}</p>
        </div>
        <a href="{{ route('recherche') }}" wire:navigate
           class="inline-flex items-center gap-1.5 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-neutral-700 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
            </svg>
            {{ __('Neue Recherche') }}
        </a>
    </div>

    <div class="space-y-4">
        @forelse ($projekte as $projekt)
            <div class="rounded-lg border border-neutral-200 p-4 transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('recherche.projekt', $projekt) }}" wire:navigate class="min-w-0 flex-1">
                        <h3 class="font-medium text-neutral-900 dark:text-neutral-100">
                            {{ str()->limit($projekt->titel, 80) }}
                        </h3>
                        @if ($projekt->review_typ)
                            <span class="mt-1 inline-block rounded bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
                                {{ $projekt->review_typ }}
                            </span>
                        @endif
                    </a>
                    <div class="flex shrink-0 items-center gap-3">
                        <span class="text-xs text-neutral-500">
                            {{ $projekt->erstellt_am?->diffForHumans() }}
                        </span>
                        <button
                            wire:click="deleteProjekt('{{ $projekt->id }}')"
                            wire:confirm="{{ __('Projekt und alle zugehörigen Daten wirklich löschen?') }}"
                            class="text-xs text-neutral-400 hover:text-red-500 dark:hover:text-red-400"
                            title="{{ __('Projekt löschen') }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-neutral-300 py-16 dark:border-neutral-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mb-4 size-10 text-neutral-300 dark:text-neutral-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Noch keine Recherche-Projekte vorhanden.') }}</p>
                <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">{{ __('Starte deine erste systematische Literaturrecherche.') }}</p>
                <a href="{{ route('recherche') }}" wire:navigate
                   class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-700 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                    {{ __('Recherche starten') }} &rarr;
                </a>
            </div>
        @endforelse
    </div>
</section>
