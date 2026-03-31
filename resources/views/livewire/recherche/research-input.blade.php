<?php

use App\Jobs\TriggerLangdockAgent;
use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $eingabe = '';

    public function starteRecherche(): void
    {
        $this->validate([
            'eingabe' => ['required', 'string', 'max:10000'],
        ]);

        $projekt = Projekt::create([
            'user_id' => Auth::id(),
            'titel' => str()->limit($this->eingabe, 120),
            'forschungsfrage' => $this->eingabe,
        ]);

        TriggerLangdockAgent::dispatch(
            Auth::id(),
            $projekt->id,
            $this->eingabe,
        );

        $this->eingabe = '';

        $this->redirect(route('recherche.projekt', $projekt), navigate: true);
    }
}; ?>

<section>
    <form wire:submit="starteRecherche" class="space-y-4">
        <div>
            <label for="eingabe" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Forschungsfrage oder Thema') }}</label>
            <textarea
                id="eingabe"
                wire:model="eingabe"
                placeholder="{{ __('Beschreibe dein Forschungsthema oder deine Frage …') }}"
                rows="4"
                required
                class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500"
            ></textarea>
        </div>

        @error('eingabe')
            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                <span wire:loading.remove>{{ __('Recherche starten') }}</span>
                <span wire:loading>{{ __('Wird erstellt …') }}</span>
            </button>
        </div>
    </form>
</section>
