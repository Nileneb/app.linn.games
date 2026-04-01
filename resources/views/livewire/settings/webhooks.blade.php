<?php

use App\Models\Webhook;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $url = '';
    public ?string $editingId = null;
    public string $editName = '';
    public string $editUrl = '';

    public function getWebhooks()
    {
        return Webhook::where('user_id', Auth::id())
            ->orderBy('created_at')
            ->get();
    }

    public function addWebhook(): void
    {
        $this->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('webhooks')->where('user_id', Auth::id()),
            ],
            'url' => ['required', 'url', 'max:500'],
        ], [
            'name.unique' => __('Ein Webhook mit diesem Namen existiert bereits.'),
        ]);

        Webhook::create([
            'user_id' => Auth::id(),
            'name'    => $this->name,
            'slug'    => Str::slug($this->name) . '-' . Str::random(6),
            'url'     => $this->url,
        ]);

        $this->name = '';
        $this->url  = '';
    }

    public function startEdit(string $id): void
    {
        $webhook = Webhook::where('user_id', Auth::id())->findOrFail($id);
        $this->editingId = $id;
        $this->editName  = $webhook->name;
        $this->editUrl   = $webhook->url;
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editName' => [
                'required', 'string', 'max:100',
                Rule::unique('webhooks')->where('user_id', Auth::id())->ignore($this->editingId),
            ],
            'editUrl' => ['required', 'url', 'max:500'],
        ], [
            'editName.unique' => __('Ein Webhook mit diesem Namen existiert bereits.'),
        ]);

        $webhook = Webhook::where('user_id', Auth::id())->findOrFail($this->editingId);
        $webhook->update([
            'name' => $this->editName,
            'url'  => $this->editUrl,
        ]);

        $this->editingId = null;
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function deleteWebhook(string $id): void
    {
        Webhook::where('user_id', Auth::id())->where('id', $id)->delete();
    }
}; ?>

<x-settings.layout :heading="__('Webhooks')" :subheading="__('Verwalte deine Langdock Webhook-Verbindungen')">
    <div class="space-y-6">
        {{-- Add new webhook --}}
        <form wire:submit="addWebhook" class="space-y-3 rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Neuen Webhook hinzufügen') }}</h3>
            <div>
                <label for="webhook_name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Name') }}</label>
                <input id="webhook_name" wire:model="name" type="text" placeholder="z.B. Dashboard Assistent" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500" />
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="webhook_url" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('URL') }}</label>
                <input id="webhook_url" wire:model="url" type="url" placeholder="https://app.langdock.com/api/hooks/workflows/..." class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500" />
                @error('url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="inline-flex items-center rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                {{ __('Hinzufügen') }}
            </button>
        </form>

        {{-- Existing webhooks --}}
        <div class="space-y-2">
            @forelse($this->getWebhooks() as $webhook)
                <div class="rounded-md border border-zinc-200 p-3 dark:border-zinc-700">
                    @if($editingId === $webhook->id)
                        <form wire:submit="saveEdit" class="space-y-2">
                            <input wire:model="editName" type="text" class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                            @error('editName') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <input wire:model="editUrl" type="url" class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                            @error('editUrl') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <div class="flex gap-2">
                                <button type="submit" class="rounded-md bg-zinc-900 px-2 py-1 text-xs font-semibold text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">{{ __('Speichern') }}</button>
                                <button type="button" wire:click="cancelEdit" class="rounded-md border border-zinc-300 px-2 py-1 text-xs dark:border-zinc-600 dark:text-zinc-300">{{ __('Abbrechen') }}</button>
                            </div>
                        </form>
                    @else
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $webhook->name }}</div>
                                <div class="mt-0.5 truncate text-xs text-zinc-400" title="{{ $webhook->url }}">{{ Str::limit($webhook->url, 60) }}</div>
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="inline-flex items-center rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">{{ $webhook->slug }}</span>
                                    <span class="text-xs text-zinc-300 dark:text-zinc-600">{{ $webhook->created_at?->format('d.m.Y') }}</span>
                                </div>
                            </div>
                            <div class="ml-3 flex shrink-0 gap-2">
                                <button wire:click="startEdit('{{ $webhook->id }}')" class="text-xs text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200">{{ __('Bearbeiten') }}</button>
                                <button wire:click="deleteWebhook('{{ $webhook->id }}')" wire:confirm="{{ __('Webhook löschen? Chat-Verlauf bleibt erhalten.') }}" class="text-xs text-zinc-400 hover:text-red-500">{{ __('Löschen') }}</button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('Noch keine Webhooks konfiguriert.') }}</p>
            @endforelse
        </div>
    </div>
</x-settings.layout>
