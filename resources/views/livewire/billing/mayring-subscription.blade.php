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
                Abo aktiviert! Erstelle unten einen API-Token um deinen Claude zu verbinden.
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

        {{-- Web-Dashboard Link --}}
        <div class="rounded-lg border border-indigo-200 dark:border-indigo-700 bg-indigo-50 dark:bg-indigo-900/20 p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-800 dark:text-indigo-300">MayringCoder Dashboard</p>
                <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-0.5">Memory durchsuchen, Analysen starten, Reports einsehen.</p>
            </div>
            <a href="{{ route('mayring.dashboard') }}" target="_blank" rel="noopener"
                class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition">
                Dashboard
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
        </div>

        {{-- Watcher-Setup Link --}}
        <div class="rounded-lg border border-emerald-200 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-900/20 p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300">Conversation-Watcher</p>
                <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-0.5">Claude-Chats automatisch aus ~/.claude/projects in Deinen Memory-Store ingesten — läuft als Docker-Container auf Deinem Rechner.</p>
            </div>
            <a href="{{ route('mayring.watcher') }}"
                class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-emerald-500 transition">
                Einrichten
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        {{-- Neuen Token erstellt --}}
        @if ($mcpToken)
            <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 p-4 space-y-3">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                    Dein neuer API-Token — nur jetzt sichtbar, danach nicht mehr:
                </p>
                <code class="block text-sm font-mono break-all text-amber-900 dark:text-amber-200 bg-amber-100 dark:bg-amber-900/40 rounded p-2 select-all">{{ $mcpToken }}</code>
            </div>
        @endif

        {{-- Token-Verwaltung --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <h3 class="font-medium text-gray-900 dark:text-white">API-Tokens</h3>

            @if ($tokens->isNotEmpty())
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($tokens as $token)
                        <div class="flex items-center justify-between py-2">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $token->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Erstellt {{ $token->created_at->diffForHumans() }}
                                    @if ($token->last_used_at)
                                        · Zuletzt genutzt {{ $token->last_used_at->diffForHumans() }}
                                    @else
                                        · Noch nie genutzt
                                    @endif
                                </p>
                            </div>
                            <x-filament::button wire:click="deleteToken({{ $token->id }})" color="danger" size="xs"
                                wire:confirm="Token '{{ $token->name }}' wirklich löschen? Verbundene Clients verlieren sofort den Zugang.">
                                Löschen
                            </x-filament::button>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Noch keine Tokens erstellt.</p>
            @endif

            <div class="flex gap-2 pt-2">
                <input type="text" wire:model="newTokenName" placeholder="Token-Name (optional)"
                    class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                <x-filament::button wire:click="createToken" color="primary" size="sm">
                    Token erstellen
                </x-filament::button>
            </div>
        </div>

        {{-- Verbindungsanleitung --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <h3 class="font-medium text-gray-900 dark:text-white">Claude verbinden</h3>

            {{-- Claude Web (claude.ai) --}}
            <div class="space-y-2">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Claude Web (claude.ai)</p>
                <div class="rounded bg-gray-50 dark:bg-gray-800 p-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Einstellungen → Connectors → Add MCP Server:</p>
                    <div class="text-xs font-mono text-gray-700 dark:text-gray-300 space-y-1">
                        <p><span class="text-gray-400">URL:</span> https://mcp.linn.games/sse</p>
                        <p><span class="text-gray-400">OAuth Client ID:</span> bene-workspace</p>
                        <p><span class="text-gray-400">OAuth Client Secret:</span> (aus Ersteinrichtung)</p>
                    </div>
                </div>
            </div>

            {{-- Claude Code --}}
            <div class="space-y-2">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Claude Code (CLI / VS Code)</p>
                <div class="rounded bg-gray-50 dark:bg-gray-800 p-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Projekt-Datei <code>.mcp.json</code>:</p>
                    <pre class="text-xs font-mono text-gray-700 dark:text-gray-300 overflow-x-auto">{
  "mcpServers": {
    "memory": {
      "url": "https://mcp.linn.games/sse"
    }
  }
}</pre>
                </div>
            </div>

            {{-- Claude Desktop --}}
            <div class="space-y-2">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Claude Desktop</p>
                <div class="rounded bg-gray-50 dark:bg-gray-800 p-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2"><code>claude_desktop_config.json</code>:</p>
                    <pre class="text-xs font-mono text-gray-700 dark:text-gray-300 overflow-x-auto">{
  "mcpServers": {
    "mayring-memory": {
      "url": "https://mcp.linn.games/sse",
      "headers": {
        "Authorization": "Bearer &lt;dein-token&gt;"
      }
    }
  }
}</pre>
                </div>
            </div>
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
