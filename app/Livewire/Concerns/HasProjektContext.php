<?php

namespace App\Livewire\Concerns;

use App\Models\Recherche\Projekt;
use Livewire\Attributes\On;

/**
 * Shared behaviour for all phase components (P1–P8).
 * Provides:
 *   - $projekt property
 *   - mount() with policy check
 *   - refreshPhaseData() listener that triggers a re-render
 */
trait HasProjektContext
{
    public Projekt $projekt;

    public function mount(Projekt $projekt): void
    {
        $this->authorize('view', $projekt);
        $this->projekt = $projekt;

        // Initialize agent dispatched state from pending records (survives page reload)
        if (property_exists($this, 'agentDispatched')) {
            $this->agentDispatched = \App\Models\PhaseAgentResult::where('projekt_id', $projekt->id)
                ->where('status', 'pending')
                ->exists();
        }
    }

    #[On('agent-result-accepted')]
    public function refreshPhaseData(): void
    {
        // Re-render triggers with() which re-queries all phase data from DB
    }
}
