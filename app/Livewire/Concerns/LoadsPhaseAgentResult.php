<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\PhaseAgentResult;

trait LoadsPhaseAgentResult
{
    /**
     * Loads the latest agent result for a given phase.
     */
    public function loadLatestAgentResult(int $phaseNr, ?string $projektId = null): ?PhaseAgentResult
    {
        $pid = $projektId ?? $this->projekt->id;

        return rescue(
            fn () => PhaseAgentResult::where('projekt_id', $pid)
                ->where('phase_nr', $phaseNr)
                ->whereNotNull('content')
                ->latest()
                ->first(),
            null,
            report: true,
        );
    }
}
