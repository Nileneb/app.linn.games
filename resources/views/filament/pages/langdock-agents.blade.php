<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Fehlerzustand --}}
        @if ($error)
            <div class="rounded-lg bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 p-4 flex items-start gap-3">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-danger-600 dark:text-danger-400 mt-0.5 shrink-0" />
                <div>
                    <p class="text-sm font-semibold text-danger-800 dark:text-danger-200">Konfigurationsfehler</p>
                    <p class="text-sm text-danger-700 dark:text-danger-300 mt-1 font-mono">{{ $error }}</p>
                </div>
            </div>
        @endif

        {{-- Konfigurierte Claude-Agenten --}}
        @if (! empty($configuredAgents))
            <div class="fi-ta overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 shadow-sm">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-white/10">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        Konfigurierte Claude-Agenten (services.anthropic.agents)
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-900">
                                <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">Config-Key</th>
                                <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">Prompt-Datei</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($configuredAgents as $key => $promptFile)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-3 font-mono text-gray-800 dark:text-gray-200 text-xs">{{ $key }}</td>
                                    <td class="px-4 py-3">
                                        <code class="text-xs bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 px-1.5 py-0.5 rounded font-mono">
                                            {{ $promptFile }}
                                        </code>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif (! $error)
            <p class="text-sm text-gray-500 dark:text-gray-400">Keine Agenten in <code>services.anthropic.agents</code> konfiguriert.</p>
        @endif

    </div>
</x-filament-panels::page>
