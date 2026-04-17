<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        {{-- Beta-Hinweis --}}
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800/50 dark:bg-amber-950/30">
            <div class="flex gap-3">
                <svg class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" clip-rule="evenodd" /></svg>
                <div class="text-sm text-amber-800 dark:text-amber-300">
                    <p class="font-medium">Geschlossene Beta</p>
                    <p class="mt-0.5 text-amber-700 dark:text-amber-400">Diese App befindet sich in der Beta-Phase. Nach der Registrierung muss dein Konto zunächst freigeschaltet werden, bevor du dich einloggen kannst.</p>
                </div>
            </div>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        @if (session('status') === 'verification-link-sent')
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/30 dark:text-green-300">
                Wir haben dir einen Bestätigungslink geschickt. Bitte prüfe dein Postfach (auch Spam).
            </div>
        @endif

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <div>
                <label for="name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Name') }}</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="{{ __('Full name') }}" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500" />
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Email Address -->
            <div>
                <label for="email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Email address') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="email@example.com" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500" />
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Forschungsfrage -->
            <div>
                <label for="forschungsfrage" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Forschungsfrage <span class="text-red-500">*</span></label>
                <textarea id="forschungsfrage" name="forschungsfrage" required rows="3" placeholder="Welche Forschungsfrage möchtest du untersuchen?" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500">{{ old('forschungsfrage') }}</textarea>
                @error('forschungsfrage') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Forschungsbereich -->
            <div>
                <label for="forschungsbereich" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Forschungsbereich <span class="text-red-500">*</span></label>
                <select id="forschungsbereich" name="forschungsbereich" required class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                    <option value="" disabled {{ old('forschungsbereich') ? '' : 'selected' }}>Bitte wählen …</option>
                    @foreach ([
                        'Gesundheit & Medizin',
                        'Psychologie & Sozialwissenschaften',
                        'Bildung & Pädagogik',
                        'Informatik & Technologie',
                        'Wirtschaft & Management',
                        'Umwelt & Nachhaltigkeit',
                        'Sonstiges',
                    ] as $bereich)
                        <option value="{{ $bereich }}" {{ old('forschungsbereich') === $bereich ? 'selected' : '' }}>{{ $bereich }}</option>
                    @endforeach
                </select>
                @error('forschungsbereich') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Erfahrung mit Literaturrecherchen -->
            <div>
                <label for="erfahrung" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Erfahrung mit Literaturrecherchen <span class="text-red-500">*</span></label>
                <select id="erfahrung" name="erfahrung" required class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                    <option value="" disabled {{ old('erfahrung') ? '' : 'selected' }}>Bitte wählen …</option>
                    @foreach ([
                        'Nein, das wäre mein erstes Mal',
                        'Ja, 1–2 Mal',
                        'Ja, regelmäßig',
                    ] as $option)
                        <option value="{{ $option }}" {{ old('erfahrung') === $option ? 'selected' : '' }}>{{ $option }}</option>
                    @endforeach
                </select>
                @error('erfahrung') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="{{ __('Password') }}" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500" />
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="{{ __('Confirm password') }}" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500" />
            </div>

            {{-- Honeypot: verstecktes Feld — Bots füllen es aus, Menschen nicht --}}
            <div class="hidden" aria-hidden="true" tabindex="-1">
                <input type="text" name="website" id="website" autocomplete="off" tabindex="-1">
            </div>

            {{-- Bot-Detection: JS-Signale --}}
            <input type="hidden" name="_timing" id="_timing" value="0">
            <input type="hidden" name="_tz" id="_tz" value="">

            <div class="flex items-center justify-end">
                <button type="submit" data-test="register-user-button" class="inline-flex w-full items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                    {{ __('Create account') }}
                </button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <a href="{{ route('login') }}" wire:navigate class="underline text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">{{ __('Log in') }}</a>
        </div>

        <p class="text-center text-xs text-zinc-400 dark:text-zinc-500">
            Bug gefunden?
            <a href="https://github.com/Nileneb/app.linn.games/issues/new" target="_blank" rel="noopener" class="underline hover:text-zinc-600 dark:hover:text-zinc-300">Direkt als GitHub-Issue melden</a>
        </p>
        <script>
            (function () {
                var _start = Date.now();
                var form = document.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function () {
                        var timingEl = document.getElementById('_timing');
                        var tzEl = document.getElementById('_tz');
                        if (timingEl) timingEl.value = Date.now() - _start;
                        if (tzEl) {
                            try { tzEl.value = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) {}
                        }
                    });
                }
            })();
        </script>
    </div>
</x-layouts.auth>
