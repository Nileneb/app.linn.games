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
        $stars    = $stats['feedback']['stars'] ?? [];
        $total_fb = $pos + $neg;
        $total_rated = array_sum($stars);
        $ratio    = $total_fb > 0 ? round($pos / $total_fb * 100) : 0;
        $avg_stars = $total_rated > 0
            ? round(array_sum(array_map(fn($k, $v) => (int)$k * $v, array_keys($stars), $stars)) / $total_rated, 1)
            : null;
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
            <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Ø Qualität</p>
            @if($avg_stars !== null)
                <p class="text-3xl font-bold mt-1 tabular-nums">{{ $avg_stars }}★</p>
                <p class="text-xs text-zinc-400 mt-1">
                    {{ $total_rated }} bewertet · {{ $pos }}+ / {{ $neg }}&minus;
                </p>
            @elseif($total_fb > 0)
                <p class="text-3xl font-bold mt-1 tabular-nums">{{ $ratio }}%</p>
                <p class="text-xs text-zinc-400 mt-1">{{ $pos }}+ / {{ $neg }}&minus;</p>
            @else
                <p class="text-3xl font-bold mt-1 text-zinc-300 dark:text-zinc-600">–</p>
                <p class="text-xs text-zinc-400 mt-1">Training-Generator ausführen</p>
            @endif
        </div>
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Ingest (24h)</p>
            <p class="text-3xl font-bold mt-1 tabular-nums">{{ $stats['ingestion']['last_24h'] ?? 0 }}</p>
            <p class="text-xs text-zinc-400 mt-1">{{ $stats['ingestion']['last_hour'] ?? 0 }} letzte Stunde</p>
        </div>
    </div>

    {{-- Pipeline Trace --}}
    @if(!empty($stats['recent_jobs']))
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="px-5 py-3 border-b border-zinc-200 dark:border-zinc-700 flex items-center gap-2">
            <span class="font-medium text-sm text-zinc-700 dark:text-zinc-300">Pipeline Trace</span>
            <span class="ml-auto text-xs text-zinc-400">letzte 5 Jobs</span>
        </div>
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach($stats['recent_jobs'] as $job)
            @php
                $statusColor = match($job['status'] ?? '') {
                    'done'    => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                    'error'   => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                    'started' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                    default   => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800',
                };
                $stageOrder = ['fetch_repo','categorize','ingest_loop','wiki_hook_error'];
                $stages = $job['stages'] ?? [];
                $allStageKeys = array_unique(array_merge($stageOrder, array_keys($stages)));
            @endphp
            <div class="px-5 py-3">
                <div class="flex items-center gap-2 mb-2">
                    <span class="font-mono text-xs text-zinc-400">{{ $job['job_id'] }}</span>
                    <span class="px-2 py-0.5 rounded text-xs font-medium {{ $statusColor }}">
                        {{ $job['status'] ?? '–' }}
                    </span>
                    @if($job['started_at'])
                    <span class="ml-auto text-xs text-zinc-400">
                        {{ \Carbon\Carbon::parse($job['started_at'])->diffForHumans() }}
                    </span>
                    @endif
                </div>
                @if(!empty($stages))
                <div class="flex flex-wrap gap-2 mt-1">
                    @foreach($allStageKeys as $key)
                    @if(isset($stages[$key]))
                    @php
                        $isError = str_contains($key, 'error');
                        $detail  = $stages[$key]['detail'] ?? '';
                        $ts      = $stages[$key]['ts'] ?? null;
                    @endphp
                    <span title="{{ $detail }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-mono
                        {{ $isError
                            ? 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400'
                            : 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' }}">
                        {{ $isError ? '⚠' : '✓' }} {{ $key }}
                        @if($detail)<span class="text-zinc-400 dark:text-zinc-500 ml-0.5">{{ Str::limit($detail, 40) }}</span>@endif
                    </span>
                    @endif
                    @endforeach
                </div>
                @else
                <span class="text-xs text-zinc-400 italic">keine Stage-Daten (Job läuft noch oder zu alt)</span>
                @endif

                {{-- v2 post-ingest hooks --}}
                @if(!empty($job['v2_jobs']))
                <div class="flex flex-wrap gap-1.5 mt-1.5">
                    @foreach($job['v2_jobs'] as $label => $v2status)
                    @php
                        $v2color = match($v2status) {
                            'done'    => 'text-green-600 dark:text-green-400',
                            'error'   => 'text-red-500',
                            'started' => 'text-blue-500 animate-pulse',
                            default   => 'text-zinc-400',
                        };
                    @endphp
                    <span class="text-xs font-mono {{ $v2color }}">↳ {{ $label }}:{{ $v2status ?? '?' }}</span>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

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
