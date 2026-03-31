<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <div>
                <label for="email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Email Address') }}</label>
                <input id="email" name="email" type="email" required autofocus placeholder="email@example.com" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500" />
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit" data-test="email-password-reset-link-button" class="inline-flex w-full items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                {{ __('Email password reset link') }}
            </button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
            <span>{{ __('Or, return to') }}</span>
            <a href="{{ route('login') }}" wire:navigate class="underline text-zinc-400 hover:text-zinc-100">{{ __('log in') }}</a>
        </div>
    </div>
</x-layouts.auth>
