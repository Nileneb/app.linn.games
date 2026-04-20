<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout
        :heading="__('LLM-Endpoints (Workspace)')"
        :subheading="__('Konfiguriere LLM-Provider für diesen Workspace. Beeinflusst MayringCoder-Recherche-Agenten. Kein Billing bei non-platform Providern.')">
        <div class="space-y-6">
            @if ($editing)
                <form wire:submit="save" class="space-y-4 rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $editingId ? __('Endpoint bearbeiten') : __('Neuen Endpoint anlegen') }}
                    </h3>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Provider') }}</label>
                        <select wire:model="provider" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach ($providers as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                        @error('provider') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Base-URL') }}</label>
                        <input wire:model="base_url" type="url" placeholder="http://host.docker.internal:11434"
                               class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        @error('base_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Model') }}</label>
                        <input wire:model="model" type="text" placeholder="qwen2.5:7b"
                               class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        @error('model') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('API-Key (optional)') }}</label>
                        <input wire:model="api_key" type="password" autocomplete="off"
                               placeholder="{{ $editingId ? __('•••••• (leer lassen = behalten)') : __('leer lassen für lokales Ollama ohne Auth') }}"
                               class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        @error('api_key') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Agent-Scope (leer = alle)') }}</label>
                        <input wire:model="agent_scope" type="text" placeholder="chat-agent"
                               class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        @error('agent_scope') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2">
                        <input wire:model="is_default" type="checkbox" class="h-4 w-4" />
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Default-Endpoint (Fallback wenn kein Agent-Scope matched)') }}</span>
                    </label>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                            {{ __('Speichern') }}
                        </button>
                        <button type="button" wire:click="cancel" class="rounded-md border border-zinc-300 px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300">
                            {{ __('Abbrechen') }}
                        </button>
                    </div>
                </form>
            @else
                <button wire:click="startCreate" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                    + {{ __('Neuer Endpoint') }}
                </button>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="pb-2 text-left font-medium text-zinc-700 dark:text-zinc-300">Provider</th>
                            <th class="pb-2 text-left font-medium text-zinc-700 dark:text-zinc-300">Model</th>
                            <th class="pb-2 text-left font-medium text-zinc-700 dark:text-zinc-300">Scope</th>
                            <th class="pb-2 text-left font-medium text-zinc-700 dark:text-zinc-300">Default</th>
                            <th class="pb-2 text-right font-medium text-zinc-700 dark:text-zinc-300">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($endpoints as $endpoint)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <td class="py-2 text-zinc-900 dark:text-zinc-100">{{ $endpoint->provider }}</td>
                                <td class="py-2 text-zinc-900 dark:text-zinc-100">{{ $endpoint->model }}</td>
                                <td class="py-2 text-zinc-600 dark:text-zinc-400">{{ $endpoint->agent_scope ?? '(alle)' }}</td>
                                <td class="py-2">
                                    @if ($endpoint->is_default)
                                        <span class="rounded bg-green-100 px-2 py-1 text-xs text-green-800 dark:bg-green-900 dark:text-green-100">default</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right space-x-2">
                                    <button wire:click="startEdit({{ $endpoint->id }})" class="text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                                        {{ __('Bearbeiten') }}
                                    </button>
                                    <button wire:click="delete({{ $endpoint->id }})"
                                            wire:confirm="Endpoint wirklich löschen?"
                                            class="text-sm text-red-600 hover:text-red-800">
                                        {{ __('Löschen') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-zinc-500">
                                    {{ __('Noch keine Endpoints. Default = Platform-managed mit Workspace-Credits.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-action-message on="llm-endpoint-saved">{{ __('Gespeichert.') }}</x-action-message>
            <x-action-message on="llm-endpoint-deleted">{{ __('Gelöscht.') }}</x-action-message>
        </div>
    </x-settings.layout>
</section>
