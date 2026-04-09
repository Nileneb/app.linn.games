<div class="flex flex-col gap-6">
    @if($invitedUser === null)
        {{-- Ungültiger oder abgelaufener Link --}}
        <div class="flex flex-col items-center gap-6 text-center">
            <div class="flex size-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                <svg class="size-8 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="flex flex-col gap-2">
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Einladung ungültig</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Dieser Einladungslink ist abgelaufen oder ungültig. Bitte wende dich an den Administrator.</p>
            </div>
            <a href="{{ route('login') }}" wire:navigate class="text-sm underline text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                Zurück zum Login
            </a>
        </div>
    @else
        {{-- Gültiger Einladungslink: Passwort-Formular --}}
        <x-auth-header title="Einladung annehmen" description="Lege dein Passwort fest, um deinen Account zu aktivieren." />

        @if(session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/30 dark:text-green-300">
                {{ session('status') }}
            </div>
        @endif

        <form wire:submit="accept" class="flex flex-col gap-6">
            <p class="text-sm text-zinc-700 dark:text-zinc-300">
                Willkommen, <span class="font-medium">{{ $invitedUser->name }}</span>! Lege dein Passwort fest, um deinen Account zu aktivieren.
            </p>

            <!-- Passwort -->
            <div>
                <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Passwort</label>
                <input
                    id="password"
                    wire:model="password"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="Mindestens 8 Zeichen"
                    class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500"
                />
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Passwort bestätigen -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Passwort bestätigen</label>
                <input
                    id="password_confirmation"
                    wire:model="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="Passwort wiederholen"
                    class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500"
                />
            </div>

            <div>
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                >
                    Account aktivieren
                </button>
            </div>
        </form>

        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            <a href="{{ route('login') }}" wire:navigate class="underline hover:text-zinc-900 dark:hover:text-zinc-100">Zurück zum Login</a>
        </div>
    @endif
</div>
