<x-layouts.app :title="__('Dashboard')">
    <div class="flex w-full flex-col gap-6 lg:h-[calc(100vh-4rem)] lg:flex-row">
        {{-- Left: Dashboard content --}}
        <div class="w-full space-y-6 lg:w-1/2 lg:overflow-y-auto">
            <div>
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Willkommen zurück, {{ auth()->user()->first_name ?? auth()->user()->name }}! 👋</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    @php
                        $now = now();
                        $projectCount = \App\Models\Recherche\Projekt::where('workspace_id', auth()->user()?->activeWorkspaceId())
                            ->where('user_id', auth()->id())
                            ->count();
                    @endphp
                    @if ($projectCount > 0)
                        Du hast <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $projectCount }}</span> {{ $projectCount === 1 ? 'Projekt' : 'Projekte' }} in deinem Workspace.
                    @else
                        Starte deine erste systematische Recherche noch heute!
                    @endif
                </p>
            </div>

            {{-- Quick links --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <a href="{{ route('recherche') }}" wire:navigate class="group rounded-lg border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Recherche') }}</span>
                        <span class="text-lg">🔍</span>
                    </div>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Neue systematische Recherche starten</p>
                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">z.B. „Auswirkungen von KI auf Bildung"</p>
                </a>
                @role('admin')
                    <a href="{{ url('/admin') }}" class="group rounded-lg border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Admin') }}</div>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Kontakte, Benutzer und Plattformeinstellungen verwalten') }}</p>
                    </a>
                @endrole
            </div>

            {{-- Recent projects --}}
            @php
                $workspaceId = auth()->user()?->activeWorkspaceId();
                $recentProjekte = collect();

                if ($workspaceId) {
                    $recentProjekte = \App\Models\Recherche\Projekt::where('workspace_id', $workspaceId)
                        ->where('user_id', auth()->id())
                        ->orderByDesc('erstellt_am')
                        ->limit(5)
                        ->get();
                }
            @endphp
            @if($recentProjekte->isNotEmpty())
                <div>
                    <h2 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Letzte Projekte') }}</h2>
                    <div class="space-y-2">
                        @foreach($recentProjekte as $projekt)
                            <a href="{{ route('recherche.projekt', $projekt) }}" wire:navigate class="block rounded-md border border-zinc-200 px-3 py-2 text-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                                <span class="text-zinc-900 dark:text-zinc-100">{{ str()->limit($projekt->titel, 80) }}</span>
                                <span class="ml-2 text-xs text-zinc-400">{{ $projekt->erstellt_am->diffForHumans() }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Right: Chat --}}
        <div class="flex w-full flex-col rounded-lg border border-zinc-200 dark:border-zinc-700 lg:h-full lg:w-1/2">
            <livewire:chat.big-research-chat />
        </div>
    </div>
</x-layouts.app>
