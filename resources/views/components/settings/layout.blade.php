<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <nav class="space-y-1">
            <a href="{{ route('profile.edit') }}" wire:navigate class="block rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('profile.edit') ? 'bg-zinc-200/70 text-zinc-900 dark:bg-zinc-700/50 dark:text-zinc-100' : 'text-zinc-700 hover:bg-zinc-200/50 dark:text-zinc-300 dark:hover:bg-zinc-700/30' }}">{{ __('Profile') }}</a>
            <a href="{{ route('user-password.edit') }}" wire:navigate class="block rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('user-password.edit') ? 'bg-zinc-200/70 text-zinc-900 dark:bg-zinc-700/50 dark:text-zinc-100' : 'text-zinc-700 hover:bg-zinc-200/50 dark:text-zinc-300 dark:hover:bg-zinc-700/30' }}">{{ __('Password') }}</a>
            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <a href="{{ route('two-factor.show') }}" wire:navigate class="block rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('two-factor.show') ? 'bg-zinc-200/70 text-zinc-900 dark:bg-zinc-700/50 dark:text-zinc-100' : 'text-zinc-700 hover:bg-zinc-200/50 dark:text-zinc-300 dark:hover:bg-zinc-700/30' }}">{{ __('Two-Factor Auth') }}</a>
            @endif
            <a href="{{ route('appearance.edit') }}" wire:navigate class="block rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('appearance.edit') ? 'bg-zinc-200/70 text-zinc-900 dark:bg-zinc-700/50 dark:text-zinc-100' : 'text-zinc-700 hover:bg-zinc-200/50 dark:text-zinc-300 dark:hover:bg-zinc-700/30' }}">{{ __('Appearance') }}</a>
        </nav>
    </div>

    <hr class="my-4 border-zinc-200 dark:border-zinc-700 md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $heading ?? '' }}</h2>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $subheading ?? '' }}</p>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
