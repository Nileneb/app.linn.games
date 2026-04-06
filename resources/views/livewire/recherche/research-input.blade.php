<?php

use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $eingabe = '';

    public function starteRecherche(): void
    {
        $this->authorize('create', Projekt::class);

        $this->validate([
            'eingabe' => ['required', 'string', 'max:10000'],
        ]);

        $workspace = Auth::user()->ensureDefaultWorkspace();

        $projekt = Projekt::create([
            'user_id' => Auth::id(),
            'workspace_id' => $workspace->id,
            'titel' => str()->limit($this->eingabe, 120),
            'forschungsfrage' => $this->eingabe,
        ]);

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
                placeholder="z.B. Wie beeinflussen digitale Lerntechnologien die akademische Leistung von Schülern in Sekundarschulen?"
                rows="4"
                required
                class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500"
            ></textarea>
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">💡 Tipp: Eine spezifische Forschungsfrage führt zu besseren Ergebnissen</p>
        </div>

        @error('eingabe')
            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        <div>
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                <span wire:loading.remove>{{ __('Recherche starten') }}</span>
                <span wire:loading style="display:none">{{ __('Wird erstellt …') }}</span>
            </button>
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Der Prozess analysiert deine Frage und erstellt eine strukturierte Recherche mit mehreren Phasen</p>
        </div>
    </form>
</section>
