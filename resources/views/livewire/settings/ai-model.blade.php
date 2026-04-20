<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $preferred_chat_model = '';

    public function mount(): void
    {
        $this->preferred_chat_model = Auth::user()->preferred_chat_model ?? '';
    }

    public function save(): void
    {
        $available = array_keys((array) config('services.anthropic.available_chat_models', []));

        $validated = $this->validate([
            'preferred_chat_model' => ['nullable', Rule::in($available)],
        ]);

        $user = Auth::user();
        $user->preferred_chat_model = $validated['preferred_chat_model'] ?: null;
        $user->save();

        $this->dispatch('chat-model-saved');
    }

    public function with(): array
    {
        return [
            'models' => (array) config('services.anthropic.available_chat_models', []),
            'defaultModel' => (string) config('services.anthropic.agent_models.chat-agent',
                config('services.anthropic.model', 'claude-sonnet-4-6')),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout
        :heading="__('Chat-Modell')"
        :subheading="__('Wähle, welches Claude-Modell für deinen Dashboard-Chat verwendet wird. Worker-Agenten (Recherche) bleiben unabhängig.')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <div class="space-y-3">
                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-zinc-200 p-4 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <input type="radio" wire:model="preferred_chat_model" value="" class="mt-1 h-4 w-4" />
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Standard verwenden') }}</span>
                            <span class="text-xs text-zinc-500">{{ $defaultModel }}</span>
                        </div>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('Administrator-Voreinstellung nutzen (wird bei Modell-Updates automatisch angepasst).') }}
                        </p>
                    </div>
                </label>

                @foreach ($models as $modelId => $meta)
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-zinc-200 p-4 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                        <input type="radio" wire:model="preferred_chat_model" value="{{ $modelId }}" class="mt-1 h-4 w-4" />
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $meta['label'] }}</span>
                                <span class="text-xs text-zinc-500">{{ $modelId }}</span>
                            </div>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $meta['description'] }}</p>
                            <p class="mt-2 text-xs text-zinc-500">
                                Input: ${{ number_format($meta['price_per_1m_input_usd'], 2) }} / 1M Tokens ·
                                Output: ${{ number_format($meta['price_per_1m_output_usd'], 2) }} / 1M Tokens
                            </p>
                        </div>
                    </label>
                @endforeach
            </div>

            @error('preferred_chat_model')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex items-center gap-4">
                <button type="submit" class="inline-flex items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                    {{ __('Speichern') }}
                </button>
                <x-action-message class="me-3" on="chat-model-saved">
                    {{ __('Gespeichert.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
