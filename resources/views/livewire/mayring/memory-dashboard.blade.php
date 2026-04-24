<div wire:poll.10s="fetchStats" class="space-y-6">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Memory Dashboard</h1>
        <span class="flex items-center gap-1.5 text-xs text-zinc-400">
            <span class="h-1.5 w-1.5 rounded-full bg-green-400 animate-pulse"></span>
            Live · alle 10s
        </span>
    </div>

    @if($error)
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 text-red-700 dark:text-red-400 text-sm">
            {{ $error }}
        </div>
    @endif

    @php
        $pos      = $stats['feedback']['positive'] ?? 0;
        $neg      = $stats['feedback']['negative'] ?? 0;
        $neutral  = $stats['feedback']['neutral']  ?? 0;
        $total_fb = $pos + $neg;
        $ratio    = $total_fb > 0 ? round($pos / $total_fb * 100) : 0;
    @endphp

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Aktive Chunks</p>
            <p class="text-3xl font-bold mt-1 tabular-nums">{{ number_format($stats['chunks']['active'] ?? 0) }}</p>
            <p class="text-xs text-zinc-400 mt-1">{{ number_format($stats['chunks']['total'] ?? 0) }} gesamt</p>
        </div>
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Quellen</p>
            <p class="text-3xl font-bold mt-1 tabular-nums">{{ number_format($stats['sources']['count'] ?? 0) }}</p>
        </div>
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Feedback Ratio</p>
            @if($total_fb > 0)
                <p class="text-3xl font-bold mt-1 tabular-nums">{{ $ratio }}%</p>
                <p class="text-xs text-zinc-400 mt-1">{{ $pos }}+ / {{ $neg }}&minus; · {{ $neutral }} abgerufen</p>
            @elseif($neutral > 0)
                <p class="text-3xl font-bold mt-1 tabular-nums text-zinc-400">{{ $neutral }}</p>
                <p class="text-xs text-zinc-400 mt-1">Chunks abgerufen · kein explicit feedback</p>
            @else
                <p class="text-3xl font-bold mt-1 text-zinc-300 dark:text-zinc-600">–</p>
                <p class="text-xs text-zinc-400 mt-1">Noch keine Suchen</p>
            @endif
        </div>
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Ingest (24h)</p>
            <p class="text-3xl font-bold mt-1 tabular-nums">{{ $stats['ingestion']['last_24h'] ?? 0 }}</p>
            <p class="text-xs text-zinc-400 mt-1">{{ $stats['ingestion']['last_hour'] ?? 0 }} letzte Stunde</p>
        </div>
    </div>

    {{-- Recent Operations Log --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="px-5 py-3 border-b border-zinc-200 dark:border-zinc-700 flex items-center gap-2">
            <span class="font-medium text-sm text-zinc-700 dark:text-zinc-300">Live Operations Log</span>
            <span class="ml-auto text-xs text-zinc-400">letzte 20 Einträge</span>
        </div>
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse($stats['recent_ops'] ?? [] as $op)
                <div class="px-5 py-2.5 flex items-center gap-3 text-sm">
                    <span class="font-mono text-xs text-zinc-400 w-28 shrink-0">
                        {{ \Carbon\Carbon::parse($op['created_at'])->diffForHumans() }}
                    </span>
                    <span @class([
                        'px-2 py-0.5 rounded text-xs font-medium shrink-0',
                        'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' => str_contains($op['event_type'] ?? '', 'ingest'),
                        'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' => str_contains($op['event_type'] ?? '', 'search'),
                        'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => !str_contains($op['event_type'] ?? '', 'ingest') && !str_contains($op['event_type'] ?? '', 'search'),
                    ])>
                        {{ $op['event_type'] ?? '–' }}
                    </span>
                    <span class="text-zinc-600 dark:text-zinc-400 truncate font-mono text-xs">
                        {{ $op['source_id'] ?? '' }}
                    </span>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-zinc-400 text-sm">Noch keine Operationen aufgezeichnet</div>
            @endforelse
        </div>
    </div>

</div>
