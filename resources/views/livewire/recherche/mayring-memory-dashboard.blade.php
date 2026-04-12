<div class="space-y-4">
    <div class="flex gap-3">
        <div class="flex-1">
            <input
                type="text"
                wire:model="searchQuery"
                wire:keydown.enter="search"
                placeholder="Forschungsgedächtnis durchsuchen…"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            >
        </div>
        <button
            wire:click="search"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
        >
            <svg wire:loading class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            Suchen
        </button>
    </div>

    @if ($error)
        <p class="text-sm text-red-500">{{ $error }}</p>
    @elseif ($searching)
        <p class="text-sm text-gray-500">Suche läuft…</p>
    @elseif (count($results))
        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach ($results as $chunk)
                <li class="py-3">
                    <div class="flex items-center justify-between text-xs text-gray-400 mb-1">
                        <span class="font-mono">{{ $chunk['source_id'] ?? '–' }}</span>
                        @isset($chunk['score'])
                            <span>{{ number_format($chunk['score'], 2) }} Ähnlichkeit</span>
                        @endisset
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300 line-clamp-3">
                        {{ $chunk['content'] ?? '' }}
                    </p>
                </li>
            @endforeach
        </ul>
    @elseif ($searchQuery)
        <p class="text-sm text-gray-500">Keine Ergebnisse für „{{ $searchQuery }}".</p>
    @else
        <p class="text-sm text-gray-400">Suche im Forschungsgedächtnis — alle ingestierten Quellen und Phasenergebnisse.</p>
    @endif
</div>
