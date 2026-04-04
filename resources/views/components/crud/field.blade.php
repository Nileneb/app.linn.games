@props([
    'label' => '',
    'required' => false,
    'sublabel' => null,
    'error' => null,
])

<div {{ $attributes->merge(['class' => '']) }}>
    <label class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
        @if ($sublabel)
            <span class="font-normal text-neutral-400">{{ $sublabel }}</span>
        @endif
    </label>
    {{ $slot }}
    @if ($error)
        <p class="mt-1 text-xs text-red-500">{{ $error }}</p>
    @endif
</div>
