<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <div>
                <label for="email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Email address') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="email@example.com" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500" />
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Password -->
            <div class="relative">
                <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="{{ __('Password') }}" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500" />
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate class="absolute top-0 end-0 text-sm underline text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>

            <!-- Remember Me -->
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} class="rounded border-zinc-300 text-zinc-900 shadow-sm focus:ring-zinc-500 dark:border-zinc-700" />
                <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Remember me') }}</span>
            </label>

            <div class="flex items-center justify-end">
                <button type="submit" data-test="login-button" class="inline-flex w-full items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                    {{ __('Log in') }}
                </button>
            </div>
        </form>

        @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Don\'t have an account?') }}</span>
                <a href="{{ route('register') }}" wire:navigate class="underline text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">{{ __('Sign up') }}</a>
            </div>
        @endif
    </div>
</x-layouts.auth>
