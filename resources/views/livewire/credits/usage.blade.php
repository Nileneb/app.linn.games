<div class="max-w-3xl mx-auto py-12 px-4 space-y-8">

    {{-- Header + Balance --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Guthaben & Verbrauch</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Workspace: {{ $workspace?->name }}</p>
        </div>
        <div class="text-right">
            <div class="text-3xl font-bold {{ ($workspace?->credits_balance_cents ?? 0) > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ number_format(($workspace?->credits_balance_cents ?? 0) / 100, 2, ',', '.') }} €
            </div>
            <a href="{{ route('credits.purchase') }}"
               class="mt-2 inline-block text-sm text-primary-600 hover:underline">
                + Aufladen
            </a>
        </div>
    </div>

    {{-- Transaktionshistorie --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 shadow-sm">
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-white/10">
            <span class="text-sm font-semibold text-gray-900 dark:text-white">Transaktionen</span>
        </div>
        @if ($transactions->isEmpty())
            <p class="px-4 py-6 text-sm text-center text-gray-500 dark:text-gray-400">
                Noch keine Transaktionen.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-900">
                            <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">Datum</th>
                            <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">Typ</th>
                            <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">Beschreibung</th>
                            <th class="px-4 py-2.5 text-right font-medium text-gray-600 dark:text-gray-300">Betrag</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($transactions as $tx)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                <td class="px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $tx->created_at?->format('d.m.Y H:i') }}
                                </td>
                                <td class="px-4 py-2.5">
                                    @if ($tx->type === 'topup')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-950 text-green-700 dark:text-green-300">
                                            Aufladung
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-400">
                                            Verbrauch
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-600 dark:text-gray-300 max-w-xs truncate">
                                    {{ $tx->description ?? $tx->agent_config_key ?? '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-xs font-medium
                                    {{ $tx->amount_cents > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-300' }}">
                                    {{ $tx->amount_cents > 0 ? '+' : '' }}{{ number_format($tx->amount_cents / 100, 4, ',', '.') }} €
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100 dark:border-white/5">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>

</div>
