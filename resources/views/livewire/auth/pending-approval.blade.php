<x-layouts.auth>
    <div class="flex flex-col items-center gap-6 text-center">
        <div class="flex size-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950/50">
            <svg class="size-8 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>

        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Registrierung erhalten</h1>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                Dein Konto wurde angelegt und wartet auf die Freischaltung durch einen Administrator.
                Du erhältst eine E-Mail, sobald du Zugang bekommst.
            </p>
        </div>

        <div class="w-full rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-left dark:border-zinc-700 dark:bg-zinc-800/50">
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Diese App befindet sich in der geschlossenen Beta. Zugänge werden manuell geprüft und freigegeben.
            </p>
        </div>

        <a href="{{ route('login') }}" class="text-sm text-zinc-500 underline hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200">
            Zurück zum Login
        </a>
    </div>
</x-layouts.auth>
