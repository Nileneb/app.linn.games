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
        @php $orphaned = $this->orphanedConfigKeys(); @endphp
        @if (! empty($orphaned))
            <div class="rounded-lg bg-warning-50 dark:bg-warning-950 border border-warning-200 dark:border-warning-800 p-4">
                <p class="text-sm font-semibold text-warning-800 dark:text-warning-200 mb-2">
                    ⚠ Lokal konfigurierte Agenten nicht in Langdock gefunden
                </p>
                <ul class="space-y-1">
                    @foreach ($orphaned as $key => $uuid)
                        <li class="text-sm text-warning-700 dark:text-warning-300 font-mono">
                            <span class="font-semibold">{{ $key }}</span> → {{ $uuid }}
                        </li>
                    @endforeach
                </ul>
                <p class="text-xs text-warning-600 dark:text-warning-400 mt-2">
                    Diese UUIDs existieren nicht in Langdock oder sind für diesen API-Key nicht sichtbar.
                    Timeouts beim Aufrufen dieser Agenten sind die Folge.
                </p>
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
                                    $configKey = $this->configKeyForId($agentId);
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
                                    <td class="px-4 py-3">
                                        <code class="text-xs bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 px-1.5 py-0.5 rounded">
                                            {{ $agentId }}
                                        </code>
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
                                @php
                                    $apiIds  = array_column($agents, 'id');
                                    $found   = in_array($uuid, $apiIds, true);
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-3 font-mono text-gray-800 dark:text-gray-200 text-xs">{{ $key }}</td>
                                    <td class="px-4 py-3">
                                        <code class="text-xs bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 px-1.5 py-0.5 rounded">
                                            {{ $uuid }}
                                        </code>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($error)
                                            <span class="text-xs text-gray-400">unbekannt (API-Fehler)</span>
                                        @elseif ($found)
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
