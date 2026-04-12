<div class="max-w-2xl mx-auto space-y-6 p-6">
    <div>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">MayringCoder Memory</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Persistentes Forschungsgedächtnis — nutzbar auf app.linn.games und in deinem eigenen Claude.
        </p>
    </div>

    {{-- Success Banner --}}
    @if ($showSuccess)
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4">
            <p class="text-sm font-medium text-green-800 dark:text-green-300">
                Abo aktiviert! Generiere dir unten einen API-Token für deinen externen Claude.
            </p>
        </div>
    @endif

    @if ($workspace->mayring_active)
        {{-- Abo aktiv --}}
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4 flex items-center justify-between">
            <div>
                <p class="font-medium text-green-800 dark:text-green-300">Abo aktiv — €5/Monat</p>
                <p class="text-xs text-green-600 dark:text-green-400 mt-0.5">Qualitative Kodierung + Memory auf der Plattform und extern aktiv.</p>
            </div>
            <x-filament::button wire:click="cancel" color="danger" size="sm"
                wire:confirm="Abo wirklich kündigen? Der Zugang bleibt bis Ende des Abrechnungszeitraums aktiv.">
                Kündigen
            </x-filament::button>
        </div>

        {{-- Externer Claude-Zugang --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <h3 class="font-medium text-gray-900 dark:text-white">Eigenen Claude verbinden</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Füge MayringCoder Memory deinem Claude Desktop, Claude Web oder Claude Code hinzu.
                Generiere einmalig einen API-Token und trage ihn in deine Claude-Konfiguration ein.
            </p>

            @if ($mcpToken)
                <div class="rounded bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 p-3">
                    <p class="text-xs font-medium text-amber-800 dark:text-amber-300 mb-1">
                        Dein API-Token — nur jetzt sichtbar, danach nicht mehr:
                    </p>
                    <code class="block text-xs font-mono break-all text-amber-900 dark:text-amber-200 select-all">{{ $mcpToken }}</code>
                </div>
            @endif

            <div class="rounded bg-gray-50 dark:bg-gray-800 p-3">
                <p class="text-xs font-medium text-gray-600 dark:text-gray-300 mb-2">Claude Desktop — <code>claude_desktop_config.json</code>:</p>
                <pre class="text-xs font-mono text-gray-700 dark:text-gray-300 overflow-x-auto">{
  "mcpServers": {
    "mayring-memory": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-fetch"],
      "env": {
        "MCP_ENDPOINT": "{{ $mcpEndpoint }}",
        "MCP_AUTH_TOKEN": "{{ $mcpToken ?? '<dein-token-hier>' }}"
      }
    }
  }
}</pre>
            </div>

            <x-filament::button wire:click="regenerateToken" color="gray" size="sm">
                {{ $hasToken ? 'Token erneuern' : 'API-Token generieren' }}
            </x-filament::button>
        </div>

    @else
        {{-- Noch kein Abo --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <p class="font-medium text-gray-900 dark:text-white">Was ist inbegriffen?</p>
            <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
                <li class="flex items-start gap-2">
                    <span class="text-green-500 mt-0.5">✓</span>
                    <span>Qualitative Mayring-Kodierung via KI — direkt in deiner Recherche</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-green-500 mt-0.5">✓</span>
                    <span>Persistentes Forschungsgedächtnis (semantische Suche über alle Projekte)</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-green-500 mt-0.5">✓</span>
                    <span>Automatische Kategorisierung — Generalisierung, Reduktion, Abstraktion</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-green-500 mt-0.5">✓</span>
                    <span>Memory in deinem eigenen Claude Desktop / Claude Web nutzbar</span>
                </li>
            </ul>

            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-gray-900 dark:text-white">€5</span>
                <span class="text-gray-500">/ Monat — jederzeit kündbar</span>
            </div>

            <x-filament::button wire:click="subscribe" color="primary">
                Jetzt abonnieren
            </x-filament::button>
        </div>

        <p class="text-xs text-gray-400 dark:text-gray-500">
            Die Plattform app.linn.games bleibt weiterhin kostenlos nutzbar.
            Das Abo schaltet nur die Memory-Funktion in deinem <em>eigenen</em> Claude frei
            und subventioniert den Betrieb der Forschungsinfrastruktur.
        </p>
    @endif
</div>
