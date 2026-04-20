<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $preferred_chat_model = '';
    public string $llm_provider_type = 'platform';
    public string $llm_endpoint = '';
    public string $llm_api_key = '';
    public string $llm_custom_model = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->preferred_chat_model = $user->preferred_chat_model ?? '';
        $this->llm_provider_type = $user->llm_provider_type ?? 'platform';
        $this->llm_endpoint = $user->llm_endpoint ?? '';
        $this->llm_api_key = $user->llm_api_key ?? '';
        $this->llm_custom_model = $user->llm_custom_model ?? '';
    }

    public function save(): void
    {
        $available = array_keys((array) config('services.anthropic.available_chat_models', []));

        $validated = $this->validate([
            'preferred_chat_model' => ['nullable', Rule::in($available)],
            'llm_provider_type' => ['required', Rule::in(['platform', 'anthropic-byo', 'openai-compatible'])],
            'llm_endpoint' => ['nullable', 'url', 'max:500', 'required_if:llm_provider_type,openai-compatible'],
            'llm_api_key' => ['nullable', 'string', 'max:500', 'required_if:llm_provider_type,anthropic-byo'],
            'llm_custom_model' => ['nullable', 'string', 'max:200', 'required_if:llm_provider_type,openai-compatible'],
        ]);

        $user = Auth::user();
        $user->preferred_chat_model = $validated['preferred_chat_model'] ?: null;
        $user->llm_provider_type = $validated['llm_provider_type'];

        if ($validated['llm_provider_type'] === 'platform') {
            $user->llm_endpoint = null;
            $user->llm_api_key = null;
            $user->llm_custom_model = null;
        } else {
            $user->llm_endpoint = $validated['llm_endpoint'] ?: null;
            // Only overwrite api_key when a new value was entered; empty string = keep existing
            if (! empty($validated['llm_api_key'])) {
                $user->llm_api_key = $validated['llm_api_key'];
            }
            $user->llm_custom_model = $validated['llm_custom_model'] ?: null;
        }

        $user->save();
        $this->llm_api_key = ''; // never expose after save

        $this->dispatch('chat-model-saved');
    }

    public function with(): array
    {
        return [
            'models' => (array) config('services.anthropic.available_chat_models', []),
            'defaultModel' => (string) config('services.anthropic.agent_models.chat-agent',
                config('services.anthropic.model', 'claude-sonnet-4-6')),
            'hasStoredKey' => ! empty(Auth::user()->llm_api_key),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout
        :heading="__('Chat-Modell & Provider')"
        :subheading="__('Wähle, welches Modell für deinen Dashboard-Chat verwendet wird und ob du einen eigenen Provider-Schlüssel nutzen willst. Worker-Agenten (Recherche) laufen immer über die Platform.')">
        <form wire:submit="save" class="my-6 w-full space-y-8">

            <!-- Modellauswahl (nur relevant für platform + anthropic-byo) -->
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Modell') }}</h3>

                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-zinc-200 p-4 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <input type="radio" wire:model="preferred_chat_model" value="" class="mt-1 h-4 w-4" />
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Standard verwenden') }}</span>
                            <span class="text-xs text-zinc-500">{{ $defaultModel }}</span>
                        </div>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('Administrator-Voreinstellung nutzen.') }}
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

                @error('preferred_chat_model')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <hr class="border-zinc-200 dark:border-zinc-700" />

            <!-- Provider -->
            <div class="space-y-4">
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Provider') }}</h3>

                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-zinc-200 p-4 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <input type="radio" wire:model.live="llm_provider_type" value="platform" class="mt-1 h-4 w-4" />
                    <div class="flex-1">
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Platform-Key (Standard)') }}</span>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('Credits werden von deinem Workspace-Guthaben abgezogen. Keine Zusatzkosten.') }}
                        </p>
                    </div>
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-zinc-200 p-4 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <input type="radio" wire:model.live="llm_provider_type" value="anthropic-byo" class="mt-1 h-4 w-4" />
                    <div class="flex-1">
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Eigener Anthropic API-Key') }}</span>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('Direkt auf deinem Anthropic-Account abrechnen. Plattform-Credits werden nicht verbraucht.') }}
                        </p>
                    </div>
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-zinc-200 p-4 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                    <input type="radio" wire:model.live="llm_provider_type" value="openai-compatible" class="mt-1 h-4 w-4" />
                    <div class="flex-1">
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('OpenAI-kompatibler Endpoint') }}</span>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('Ollama, OpenRouter, LM Studio, vLLM — alles, was /v1/chat/completions spricht.') }}
                        </p>
                    </div>
                </label>

                @error('llm_provider_type')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- BYO Anthropic fields -->
            @if ($llm_provider_type === 'anthropic-byo')
                <div class="space-y-3 rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div>
                        <label for="llm_api_key" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Anthropic API-Key') }}
                        </label>
                        <input id="llm_api_key" wire:model="llm_api_key" type="password" autocomplete="off"
                               placeholder="{{ $hasStoredKey ? __('•••••• (gespeichert — leer lassen = behalten)') : 'sk-ant-...' }}"
                               class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500" />
                        @error('llm_api_key') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-zinc-500">{{ __('Verschlüsselt in der Datenbank gespeichert (Laravel encrypted cast).') }}</p>
                    </div>
                </div>
            @endif

            <!-- Custom Endpoint fields -->
            @if ($llm_provider_type === 'openai-compatible')
                <div class="space-y-3 rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div>
                        <label for="llm_endpoint" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Endpoint-URL') }}
                        </label>
                        <input id="llm_endpoint" wire:model="llm_endpoint" type="url" placeholder="http://host.docker.internal:11434"
                               class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                        @error('llm_endpoint') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-zinc-500">{{ __('Basis-URL ohne /v1/chat/completions — Beispiele: http://localhost:11434, https://openrouter.ai/api') }}</p>
                    </div>

                    <div>
                        <label for="llm_custom_model" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Model-Name') }}
                        </label>
                        <input id="llm_custom_model" wire:model="llm_custom_model" type="text" placeholder="qwen2.5:7b"
                               class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                        @error('llm_custom_model') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="llm_api_key" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('API-Key (optional)') }}
                        </label>
                        <input id="llm_api_key" wire:model="llm_api_key" type="password" autocomplete="off"
                               placeholder="{{ $hasStoredKey ? __('•••••• (gespeichert)') : __('Leer lassen für Endpoints ohne Auth (z.B. lokales Ollama)') }}"
                               class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                        @error('llm_api_key') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif

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
