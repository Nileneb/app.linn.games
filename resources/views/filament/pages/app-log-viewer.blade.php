<x-filament-panels::page>
    <div class="space-y-4">

        {{-- Filter --}}
        <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Level:</label>
            <select
                wire:model.live="filterLevel"
                class="text-sm rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
                @foreach ($this->getLevelOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <span class="text-xs text-gray-400 dark:text-gray-500">{{ count($logLines) }} Einträge</span>
        </div>

        {{-- Log Lines --}}
        <div class="fi-ta overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 shadow-sm">
            @if (empty($logLines))
                <p class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Keine Log-Einträge gefunden.</p>
            @else
                <div class="overflow-x-auto max-h-[70vh] overflow-y-auto">
                    <table class="w-full text-xs font-mono">
                        <tbody>
                            @foreach ($logLines as $entry)
                                @php
                                    $rowClass = match(true) {
                                        in_array($entry['level'], ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'])
                                            => 'bg-danger-50 dark:bg-danger-950/40 text-danger-800 dark:text-danger-200',
                                        $entry['level'] === 'WARNING'
                                            => 'bg-warning-50 dark:bg-warning-950/40 text-warning-800 dark:text-warning-200',
                                        $entry['level'] === 'DEBUG'
                                            => 'text-gray-400 dark:text-gray-600',
                                        default
                                            => 'text-gray-700 dark:text-gray-300',
                                    };
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-white/5 {{ $rowClass }}">
                                    <td class="px-3 py-1.5 whitespace-pre-wrap break-all">{{ $entry['raw'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</x-filament-panels::page>
