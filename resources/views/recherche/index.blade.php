<x-layouts.app :title="__('Recherche')">
    <div class="mx-auto w-full max-w-4xl space-y-8">
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Recherche') }}</h1>

        <livewire:recherche.research-input />

        <hr class="border-zinc-200 dark:border-zinc-700" />

        <livewire:recherche.projekt-liste />
    </div>
</x-layouts.app>
