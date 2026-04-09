{{-- Phase Agent Trigger Button — fire-and-forget, stays disabled until job completes --}}
@props(['phaseNr', 'dispatched' => false])

<div @if($dispatched) wire:poll.5s="checkAgentStatus" @endif>
    @if ($dispatched)
        <p class="inline-flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
            <svg class="h-4 w-4 animate-spin text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            {{ __('KI läuft im Hintergrund…') }}
        </p>
    @else
        <button
            wire:click="triggerAgent({{ $phaseNr }})"
            wire:loading.attr="disabled"
            wire:target="triggerAgent"
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="triggerAgent">✨ {{ __('KI aufrufen') }}</span>
            <span wire:loading wire:target="triggerAgent" class="inline-flex items-center gap-1">
                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                {{ __('Wird gestartet…') }}
            </span>
        </button>
    @endif
</div>
