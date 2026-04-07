{{-- Phase Agent Trigger Button --}}
@props(['phaseNr', 'componentName' => null, 'agentConfigKey' => null])

<div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
    <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.099-3.099L2.25 12l2.846-.813a4.5 4.5 0 003.099-3.099L9 5.25l.813 2.846a4.5 4.5 0 003.099 3.099L15.75 12l-2.846.813a4.5 4.5 0 00-3.099 3.099l-.813 2.846zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>
        </svg>
        <div class="flex-1">
            <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100">KI-Vorschlag</h4>
            <p class="mt-1 text-xs text-blue-700 dark:text-blue-300">Lass die KI Vorschläge basierend auf deinen Eingaben generieren</p>
            <button
                wire:click="triggerAgent({{ $phaseNr }})"
                wire:loading.attr="disabled"
                class="mt-2 inline-flex items-center gap-2 rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                <span wire:loading.remove>✨ KI aufrufen</span>
                <span wire:loading class="inline-flex items-center gap-1">
                    <svg class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Wird verarbeitet…
                </span>
            </button>
        </div>
    </div>
</div>
