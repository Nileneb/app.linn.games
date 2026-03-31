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
        <flux:textarea
            wire:model="eingabe"
            label="{{ __('Forschungsfrage oder Thema') }}"
            placeholder="{{ __('Beschreibe dein Forschungsthema oder deine Frage …') }}"
            rows="4"
            required
        />

        @error('eingabe')
            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        <div class="flex items-center gap-4">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('Recherche starten') }}</span>
                <span wire:loading>{{ __('Wird erstellt …') }}</span>
            </flux:button>
        </div>
    </form>
</section>
