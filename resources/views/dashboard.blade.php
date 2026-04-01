<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 lg:flex-row">
        {{-- Left: Dashboard content --}}
        <div class="w-full space-y-6 lg:w-1/2">
            <div>
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Willkommen zurück, :name', ['name' => auth()->user()->name]) }}</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Dein Forschungs-Dashboard') }}</p>
            </div>

            {{-- Quick links --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <a href="{{ route('recherche') }}" class="group rounded-lg border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Recherche') }}</div>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Neue systematische Recherche starten oder bestehende Projekte verwalten') }}</p>
                </a>
                @can('viewAny', \App\Models\Contact::class)
                    <a href="/admin" class="group rounded-lg border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Admin') }}</div>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Kontakte, Benutzer und Plattformeinstellungen verwalten') }}</p>
                    </a>
                @endcan
            </div>

            {{-- Recent projects --}}
            @php
                $recentProjekte = \App\Models\Recherche\Projekt::where('user_id', auth()->id())
                    ->orderByDesc('erstellt_am')
                    ->limit(5)
                    ->get();
            @endphp
            @if($recentProjekte->isNotEmpty())
                <div>
                    <h2 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Letzte Projekte') }}</h2>
                    <div class="space-y-2">
                        @foreach($recentProjekte as $projekt)
                            <a href="{{ route('recherche.projekt', $projekt) }}" class="block rounded-md border border-zinc-200 px-3 py-2 text-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                                <span class="text-zinc-900 dark:text-zinc-100">{{ str()->limit($projekt->titel, 80) }}</span>
                                <span class="ml-2 text-xs text-zinc-400">{{ $projekt->erstellt_am->diffForHumans() }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Right: Chat --}}
        <div class="flex w-full flex-col rounded-lg border border-zinc-200 dark:border-zinc-700 lg:w-1/2" style="min-height: calc(100vh - 10rem);">
            <livewire:chat.big-research-chat />
        </div>
    </div>
</x-layouts.app>
