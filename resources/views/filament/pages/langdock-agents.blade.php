<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Fehlerzustand --}}
        @if ($error)
            <div class="rounded-lg bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 p-4 flex items-start gap-3">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-danger-600 dark:text-danger-400 mt-0.5 shrink-0" />
                <div>
                    <p class="text-sm font-semibold text-danger-800 dark:text-danger-200">Fehler beim Abruf der Agenten-Liste</p>
                    <p class="text-sm text-danger-700 dark:text-danger-300 mt-1 font-mono">{{ $error }}</p>
                </div>
            </div>
        @endif

        {{-- Lokal konfiguriert, aber nicht in API vorhanden --}}
        @if (! empty($orphaned))
            <div class="rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 p-4 flex items-start gap-3">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-amber-500 dark:text-amber-400 mt-0.5 shrink-0" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-200 mb-2">
                        Lokal konfigurierte Agenten nicht in Langdock gefunden
                    </p>
                    <ul class="space-y-1">
                        @foreach ($orphaned as $key => $uuid)
                            <li class="text-sm text-amber-700 dark:text-amber-300">
                                <code class="font-mono text-xs bg-amber-100/70 dark:bg-amber-900/30 px-1 py-0.5 rounded">{{ $key }}</code>
                                →
                                <span class="font-mono text-xs" title="{{ $uuid }}">{{ substr($uuid, 0, 8) }}…{{ substr($uuid, -4) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-2">
                        Diese UUIDs existieren nicht in Langdock oder sind für diesen API-Key nicht sichtbar.
                        Timeouts beim Aufrufen dieser Agenten sind die Folge.
                    </p>
                </div>
            </div>
        @endif

        {{-- Agenten-Tabelle --}}
        @if (! empty($agents))
            <div class="fi-ta overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-white/10">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ count($agents) }} Agenten in Langdock
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Gecacht für 5 Minuten</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-900">
                                <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">Name</th>
                                <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">Agent-ID</th>
                                <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">Lokaler Config-Key</th>
                                <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($agents as $agent)
                                @php
                                    $agentId  = $agent['id'] ?? '';
                                    $configKey = $configKeyMap[$agentId] ?? null;
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                        {{ $agent['name'] ?? '—' }}
                                        @if (isset($agent['description']) && $agent['description'])
                                            <p class="text-xs text-gray-500 dark:text-gray-400 font-normal mt-0.5">
                                                {{ \Illuminate\Support\Str::limit($agent['description'], 80) }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3" x-data="{ copied: false }">
                                        <div class="flex items-center gap-1.5">
                                            <code class="font-mono text-xs bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 px-1.5 py-0.5 rounded"
                                                  title="{{ $agentId }}">
                                                {{ substr($agentId, 0, 8) }}…{{ substr($agentId, -4) }}
                                            </code>
                                            <button @click="navigator.clipboard.writeText('{{ $agentId }}'); copied=true; setTimeout(()=>copied=false,2000)"
                                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
                                                    title="UUID kopieren">
                                                <svg x-show="!copied" class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                                </svg>
                                                <svg x-show="copied" class="h-3.5 w-3.5 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($configKey)
                                            <span class="inline-flex items-center gap-1 text-xs bg-success-100 dark:bg-success-950 text-success-800 dark:text-success-300 px-2 py-0.5 rounded-full font-medium">
                                                ✓ {{ $configKey }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-600">nicht konfiguriert</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @php $status = $agent['status'] ?? $agent['state'] ?? null; @endphp
                                        @if ($status)
                                            <span @class([
                                                'inline-block text-xs px-2 py-0.5 rounded-full font-medium',
                                                'bg-success-100 dark:bg-success-950 text-success-800 dark:text-success-300' => in_array(strtolower($status), ['active', 'published', 'enabled']),
                                                'bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-400' => ! in_array(strtolower($status), ['active', 'published', 'enabled']),
                                            ])>
                                                {{ $status }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif (! $error)
            <p class="text-sm text-gray-500 dark:text-gray-400">Keine Agenten gefunden.</p>
        @endif

        {{-- Lokal konfigurierte Agenten (Übersicht) --}}
        @if (! empty($configuredAgents))
            <div class="fi-ta overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 shadow-sm">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-white/10">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        Lokal konfigurierte Agenten (config/services.php)
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-900">
                                <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">Config-Key</th>
                                <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">UUID</th>
                                <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">In Langdock gefunden</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($configuredAgents as $key => $uuid)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-3">
                                        <code class="font-mono text-xs bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 px-1.5 py-0.5 rounded">{{ $key }}</code>
                                    </td>
                                    <td class="px-4 py-3" x-data="{ copied: false }">
                                        <div class="flex items-center gap-1.5">
                                            <code class="font-mono text-xs bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 px-1.5 py-0.5 rounded"
                                                  title="{{ $uuid }}">
                                                {{ substr($uuid, 0, 8) }}…{{ substr($uuid, -4) }}
                                            </code>
                                            <button @click="navigator.clipboard.writeText('{{ $uuid }}'); copied=true; setTimeout(()=>copied=false,2000)"
                                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
                                                    title="UUID kopieren">
                                                <svg x-show="!copied" class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                                </svg>
                                                <svg x-show="copied" class="h-3.5 w-3.5 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($error)
                                            <span class="text-xs text-gray-400">unbekannt (API-Fehler)</span>
                                        @elseif (! isset($orphaned[$key]))
                                            <span class="inline-flex items-center gap-1 text-xs bg-success-100 dark:bg-success-950 text-success-800 dark:text-success-300 px-2 py-0.5 rounded-full font-medium">✓ ja</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs bg-danger-100 dark:bg-danger-950 text-danger-800 dark:text-danger-300 px-2 py-0.5 rounded-full font-medium">✗ nein</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
