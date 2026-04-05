<x-layouts.app :title="__('Mayring-Codierung') . ' – ' . $projekt->titel">
    <div class="mx-auto w-full max-w-4xl space-y-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('recherche.projekt', $projekt) }}" wire:navigate
               class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                &larr; {{ $projekt->titel }}
            </a>
        </div>
        <livewire:recherche.mayring-codierung :projekt="$projekt" />
    </div>
</x-layouts.app>
