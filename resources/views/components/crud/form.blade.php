@props([
    'visible' => false,
    'saveAction' => '',
    'cancelAction' => '',
    'saveLabel' => 'Speichern',
    'cancelLabel' => 'Abbrechen',
])

@if ($visible)
    <div class="border-b border-neutral-200 bg-blue-50/50 p-4 dark:border-neutral-700 dark:bg-blue-950/20">
        {{-- Form Fields Slot --}}
        {{ $slot }}

        {{-- Action Buttons --}}
        <div class="mt-3 flex gap-2">
            <button wire:click="{{ $saveAction }}" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">{{ $saveLabel }}</button>
            <button wire:click="{{ $cancelAction }}" class="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">{{ $cancelLabel }}</button>
        </div>
    </div>
@endif
