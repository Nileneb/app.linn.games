<?php

use App\Models\Webhook;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    // Dashboard Chat Slot
    public string $dashboardUrl = '';
    public bool $dashboardSaved = false;

    // Recherche starten Slot
    public string $rechercheUrl = '';
    public bool $rechercheSaved = false;

    public function mount(): void
    {
        $userId = Auth::id();
        $this->dashboardUrl  = Webhook::forUser($userId, 'dashboard_chat')?->url ?? '';
        $this->rechercheUrl  = Webhook::forUser($userId, 'recherche_start')?->url ?? '';
    }

    public function saveDashboard(): void
    {
        $this->validate([
            'dashboardUrl' => ['required', 'url', 'max:500'],
        ]);

        $userId = Auth::id();
        $webhook = Webhook::where('user_id', $userId)->where('frontend_object', 'dashboard_chat')->first();

        if ($webhook) {
            $webhook->update(['url' => $this->dashboardUrl]);
        } else {
            Webhook::create([
                'user_id'         => $userId,
                'frontend_object' => 'dashboard_chat',
                'name'            => 'Dashboard Chat',
                'slug'            => 'dashboard-chat-' . Str::random(8),
                'url'             => $this->dashboardUrl,
            ]);
        }

        $this->dashboardSaved = true;
        $this->dispatch('dashboard-saved');
    }

    public function saveRecherche(): void
    {
        $this->validate([
            'rechercheUrl' => ['required', 'url', 'max:500'],
        ]);

        $userId = Auth::id();
        $webhook = Webhook::where('user_id', $userId)->where('frontend_object', 'recherche_start')->first();

        if ($webhook) {
            $webhook->update(['url' => $this->rechercheUrl]);
        } else {
            Webhook::create([
                'user_id'         => $userId,
                'frontend_object' => 'recherche_start',
                'name'            => 'Recherche starten',
                'slug'            => 'recherche-start-' . Str::random(8),
                'url'             => $this->rechercheUrl,
            ]);
        }

        $this->rechercheSaved = true;
        $this->dispatch('recherche-saved');
    }

    public function clearDashboard(): void
    {
        Webhook::where('user_id', Auth::id())->where('frontend_object', 'dashboard_chat')->delete();
        $this->dashboardUrl   = '';
        $this->dashboardSaved = false;
    }

    public function clearRecherche(): void
    {
        Webhook::where('user_id', Auth::id())->where('frontend_object', 'recherche_start')->delete();
        $this->rechercheUrl   = '';
        $this->rechercheSaved = false;
    }
}; ?>

<x-settings.layout :heading="__('Webhooks')" :subheading="__('Weise jedem Frontend-Objekt einen Langdock-Webhook zu')">
    <div class="space-y-6">

        {{-- Slot 1: Dashboard Chat --}}
        <div class="rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="mb-3">
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Dashboard Chat') }}</h3>
                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Wird im Chat-Fenster auf der Dashboard-Seite verwendet.') }}</p>
            </div>
            <form wire:submit="saveDashboard" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Webhook-URL') }}</label>
                    <input
                        wire:model="dashboardUrl"
                        type="url"
                        placeholder="https://app.langdock.com/api/hooks/workflows/..."
                        class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500"
                    />
                    @error('dashboardUrl') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex items-center rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                        {{ __('Speichern') }}
                    </button>
                    @if($dashboardUrl)
                        <button type="button" wire:click="clearDashboard" wire:confirm="{{ __('Dashboard-Chat-Webhook wirklich entfernen?') }}" class="inline-flex items-center rounded-md border border-zinc-300 px-3 py-2 text-sm text-zinc-500 hover:text-red-500 dark:border-zinc-600 dark:text-zinc-400">
                            {{ __('Entfernen') }}
                        </button>
                    @endif
                </div>
            </form>
            <div class="mt-2">
                @if($dashboardUrl && !$errors->has('dashboardUrl'))
                    <p class="text-xs text-green-600 dark:text-green-400">&#10003; {{ __('Konfiguriert') }}</p>
                @else
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Noch nicht konfiguriert') }}</p>
                @endif
            </div>
        </div>

        {{-- Slot 2: Recherche starten --}}
        <div class="rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="mb-3">
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Recherche starten') }}</h3>
                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Wird beim Starten einer neuen Recherche ausgelöst (KI-Agent).') }}</p>
            </div>
            <form wire:submit="saveRecherche" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Webhook-URL') }}</label>
                    <input
                        wire:model="rechercheUrl"
                        type="url"
                        placeholder="https://app.langdock.com/api/hooks/workflows/..."
                        class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500"
                    />
                    @error('rechercheUrl') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex items-center rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                        {{ __('Speichern') }}
                    </button>
                    @if($rechercheUrl)
                        <button type="button" wire:click="clearRecherche" wire:confirm="{{ __('Recherche-Webhook wirklich entfernen?') }}" class="inline-flex items-center rounded-md border border-zinc-300 px-3 py-2 text-sm text-zinc-500 hover:text-red-500 dark:border-zinc-600 dark:text-zinc-400">
                            {{ __('Entfernen') }}
                        </button>
                    @endif
                </div>
            </form>
            <div class="mt-2">
                @if($rechercheUrl && !$errors->has('rechercheUrl'))
                    <p class="text-xs text-green-600 dark:text-green-400">&#10003; {{ __('Konfiguriert') }}</p>
                @else
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Noch nicht konfiguriert') }}</p>
                @endif
            </div>
        </div>

    </div>
</x-settings.layout>
