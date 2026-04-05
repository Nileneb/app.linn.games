@props([
    'visible' => false,
    'saveAction' => '',
    'cancelAction' => '',
    'saveLabel' => 'Speichern',
    'cancelLabel' => 'Abbrechen',
    'title' => '',
])

@if ($visible)
    <div class="fixed inset-0 z-30 bg-black/30" wire:click="{{ $cancelAction }}"></div>
    <div class="fixed inset-y-0 right-0 z-40 flex w-full flex-col overflow-hidden bg-white shadow-2xl dark:bg-zinc-900 sm:max-w-md">
        <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ $title }}</h3>
            <button wire:click="{{ $cancelAction }}" class="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
            {{-- Form Fields Slot --}}
            {{ $slot }}
        </div>
        <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
            <div class="flex justify-end gap-2">
                <button wire:click="{{ $cancelAction }}" class="rounded px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">{{ $cancelLabel }}</button>
                <button wire:click="{{ $saveAction }}" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ $saveLabel }}</button>
            </div>
        </div>
    </div>
@endif
