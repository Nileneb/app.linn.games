<?php

namespace App\Services;

readonly class TransitionStatus
{
    public function __construct(
        public bool $isReady,
        public bool $isBlocking,
        public string $warningMessage,
        public array $missingItems,
    ) {}
}
