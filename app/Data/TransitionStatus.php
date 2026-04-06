<?php

namespace App\Data;

/**
 * TransitionStatus DTO
 * Repräsentiert den Status eines Phase-Übergangs für UI-Darstellung.
 */
class TransitionStatus
{
    public function __construct(
        public bool $isReady,
        public bool $isBlocking,
        public ?string $warningMessage = null,
        public array $counts = [],
        public array $thresholdDetails = [],
    ) {}

    public static function fromValidation(array $validation): self
    {
        return new self(
            isReady: $validation['can_transition'] && ! $validation['warning'],
            isBlocking: $validation['is_blocking'],
            warningMessage: $validation['warning'],
            counts: $validation['counts'],
            thresholdDetails: $validation['threshold_details'],
        );
    }
}
