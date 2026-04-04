<?php

use App\Actions\SendAgentMessage;
use App\Models\Recherche\Projekt;
use Livewire\Volt\Component;

new class extends Component {
    public Projekt $projekt;
    public string $agentConfigKey;
    public string $label;
    public int $phaseNr;

    public bool $showModal = false;
    public string $result = '';
    public string $error = '';

    public function runAgent(): void
    {
        $this->error = '';
        $this->result = '';
        $this->showModal = true;

        $messages = $this->buildContextMessages();

        $result = app(SendAgentMessage::class)->execute($this->agentConfigKey, $messages, 120, [
            'source' => 'recherche_phase_agent',
            'projekt_id' => $this->projekt->id,
            'workspace_id' => $this->projekt->workspace_id,
            'phase_nr' => $this->phaseNr,
            'user_id' => $this->projekt->user_id,
            'label' => $this->label,
        ]);

        if ($result['success']) {
            $this->result = trim($result['content']);
        } else {
            $this->error = trim($result['content']);
        }
    }

    public function acceptResult(): void
    {
        $this->dispatch('agent-result-accepted', result: $this->result, phaseNr: $this->phaseNr);
        $this->showModal = false;
        $this->result = '';
    }

    public function dismissResult(): void
    {
        $this->showModal = false;
        $this->result = '';
        $this->error = '';
    }

    protected function buildContextMessages(): array
    {
        $context = "Forschungsfrage: {$this->projekt->forschungsfrage}";

        if ($this->projekt->review_typ) {
            $context .= "\nReview-Typ: {$this->projekt->review_typ}";
        }

        return [
            ['role' => 'user', 'content' => $context],
        ];
    }
}; ?>

<div>
    <button
        wire:click="runAgent"
        wire:loading.attr="disabled"
        wire:target="runAgent"
        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
    >
        <span wire:loading.remove wire:target="runAgent">{{ $label }}</span>
        <span wire:loading wire:target="runAgent" class="inline-flex items-center gap-1">
            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            {{ __('KI arbeitet…') }}
        </span>
    </button>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-2xl dark:bg-neutral-800">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4 dark:border-neutral-700">
                    <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ $label }} — {{ __('Ergebnis') }}
                    </h3>
                    <button wire:click="dismissResult" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="max-h-[60vh] overflow-y-auto px-6 py-4">
                    @if ($error)
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                            <p class="text-sm text-red-700 dark:text-red-400">{{ $error }}</p>
                        </div>
                    @elseif ($result)
                        <div class="prose prose-sm max-w-none dark:prose-invert">
                            {!! nl2br(e($result)) !!}
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="flex justify-end gap-3 border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                    <button wire:click="dismissResult" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-700">
                        {{ __('Verwerfen') }}
                    </button>
                    @if ($result && ! $error)
                        <button wire:click="acceptResult" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                            {{ __('Übernehmen') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
