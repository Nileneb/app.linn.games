<div class="mx-auto max-w-3xl space-y-8 p-6">
    <header class="space-y-3">
        <h1 class="text-2xl font-bold">Conversation-Watcher einrichten</h1>
        <p class="text-zinc-600 dark:text-zinc-300">
            Der Watcher läuft als kleiner Container <strong>auf Deinem Rechner</strong>
            und verwandelt Deine Claude-Chats automatisch in durchsuchbares Wissen.
            Er liest Dein lokales <code>~/.claude/projects/*.jsonl</code>, erstellt
            alle paar Minuten eine Kurzzusammenfassung und schickt sie an unseren
            zentralen Memory-Server.
        </p>
    </header>

    <section class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-5 space-y-3">
        <h2 class="text-lg font-semibold">Was der Watcher für Dich tut</h2>
        <ul class="list-disc list-inside space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
            <li>Fängt neue Claude-Turns im Hintergrund ab — Du musst nichts kopieren.</li>
            <li>Stoppt die GPU-Anforderung im Leerlauf — über Nacht passiert nichts Teures.</li>
            <li>Füttert MayringCoders Predictive/Ambient-Layer mit realen Session-Daten,
                damit Suchen und Vorschläge klüger werden.</li>
            <li>Dedupt automatisch — zweimal gestartet, nichts doppelt.</li>
        </ul>
        <p class="text-xs text-zinc-500 dark:text-zinc-400">
            <strong>Wichtig:</strong> Der Watcher braucht Docker Desktop (Mac/Windows)
            oder eine Docker-Engine (Linux). Es wird kein Code auf Deinem Rechner
            ausgeführt außer dem offiziellen MayringCoder-Image.
        </p>
    </section>

    <section class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-5 space-y-4">
        <h2 class="text-lg font-semibold">1. Token erzeugen</h2>
        <p class="text-sm text-zinc-600 dark:text-zinc-300">
            Der Token identifiziert Deinen Laptop gegenüber unserem Server. Er gilt
            30 Tage und ist an Deinen Account gebunden. Nach Ablauf einfach neu
            generieren.
        </p>

        @if ($generatedToken)
            <div class="rounded bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 p-3 text-sm">
                <p class="font-medium">Token erstellt. Gültig bis: {{ $expiresAt }}</p>
                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                    Wird nur einmal hier angezeigt — kopier ihn direkt in den Command
                    unten oder speichere ihn sicher (z.B. im Passwort-Manager).
                </p>
            </div>
        @else
            <x-filament::button wire:click="generateToken" color="primary">
                Watcher-Token generieren
            </x-filament::button>
        @endif
    </section>

    @if ($generatedToken)
        <section class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-5 space-y-4">
            <h2 class="text-lg font-semibold">2. Watcher starten</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Kopier den Befehl ins Terminal. API-URL und Projekt-Pfad sind
                fest eingebaut — nur Dein Token ist drin. Keine Config-Datei nötig.
            </p>
            <pre class="bg-zinc-900 text-zinc-100 p-4 rounded text-xs overflow-x-auto whitespace-pre select-all"
>docker run -d --name mayring-watcher --restart=unless-stopped \
  -v ~/.claude/projects:/host_claude:ro \
  -v mayring-watcher-state:/root/.cache/mayryngcoder \
  -e CLAUDE_PROJECTS_DIR=/host_claude \
  -e MAYRING_API_URL={{ $apiBaseUrl }} \
  -e MAYRING_JWT={{ $generatedToken }} \
  nileneb/mayring:latest \
  python -m tools.conversation_watcher --workspace-id system</pre>

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Läuft er? <code>docker logs -f mayring-watcher</code><br>
                Stoppen &amp; entfernen: <code>docker rm -f mayring-watcher</code>
            </p>
        </section>

        <section class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-4 text-sm">
            <strong>Nach 30 Tagen:</strong> Token neu generieren, dann
            <code>docker rm -f mayring-watcher</code> und den neuen
            <code>docker run</code>-Befehl nochmal ausführen.
        </section>
    @endif
</div>
