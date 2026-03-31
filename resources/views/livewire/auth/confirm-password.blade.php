<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Confirm password')"
            :description="__('This is a secure area of the application. Please confirm your password before continuing.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <div>
                <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="{{ __('Password') }}" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500" />
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit" data-test="confirm-password-button" class="inline-flex w-full items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                {{ __('Confirm') }}
            </button>
        </form>
    </div>
</x-layouts.auth>
