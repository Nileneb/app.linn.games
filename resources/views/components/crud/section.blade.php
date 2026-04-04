@props([
    'title' => '',
    'count' => 0,
    'newAction' => null,
    'newLabel' => '+ Neu',
])

<div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
    {{-- Header --}}
    <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
        <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
            {{ $title }}
            <span class="ml-1 text-xs font-normal text-neutral-500">({{ $count }})</span>
        </h3>
        @if ($newAction)
            <button wire:click="{{ $newAction }}" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">{{ $newLabel }}</button>
        @endif
    </div>

    {{-- Content Slot --}}
    {{ $slot }}
</div>
