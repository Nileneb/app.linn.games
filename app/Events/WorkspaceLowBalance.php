<?php

namespace App\Events;

use App\Models\Workspace;
use Illuminate\Foundation\Events\Dispatchable;

class WorkspaceLowBalance
{
    use Dispatchable;

    public function __construct(
        public readonly Workspace $workspace,
        public readonly int $balanceCents,
        public readonly int $thresholdPercent,
    ) {}
}
