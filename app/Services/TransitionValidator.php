<?php

namespace App\Services;

use App\Models\Recherche\Projekt;

class TransitionValidator
{
    public function __construct(
        private readonly PhaseCountService $countService,
    ) {}

    public function validate(Projekt $projekt, int $fromPhaseNr): TransitionStatus
    {
        $config = config("phase_chain.thresholds.{$fromPhaseNr}");

        // No threshold configured for this phase → always ready
        if (! $config) {
            return new TransitionStatus(
                isReady: true,
                isBlocking: false,
                warningMessage: '',
                missingItems: [],
            );
        }

        $counts = $this->countService->countForPhase($projekt, $fromPhaseNr);
        $missingItems = [];

        // Check all threshold keys
        foreach ($config as $key => $threshold) {
            if ($key === 'blocking' || $key === 'warning' || $key === 'agent_check') {
                continue;
            }

            // Map threshold key to count key (e.g., 'min_components' → 'components')
            $countKey = substr($key, 4); // Remove 'min_' prefix
            $currentCount = $counts[$countKey] ?? 0;

            if ($currentCount < $threshold) {
                $missingItems[$key] = [
                    'threshold' => $threshold,
                    'current'   => $currentCount,
                ];
            }
        }

        $isReady = empty($missingItems);
        $isBlocking = ! $isReady && ($config['blocking'] ?? false);
        $warningMessage = $config['warning'] ?? '';

        return new TransitionStatus(
            isReady: $isReady,
            isBlocking: $isBlocking,
            warningMessage: $warningMessage,
            missingItems: $missingItems,
        );
    }
}
