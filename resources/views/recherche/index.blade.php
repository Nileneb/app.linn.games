<x-layouts.app :title="__('Recherche')">
    <div class="mx-auto w-full max-w-4xl space-y-8">
        <flux:heading size="xl">{{ __('Recherche') }}</flux:heading>

        <livewire:recherche.research-input />

        <flux:separator />

        <livewire:recherche.projekt-liste />
    </div>
</x-layouts.app>
