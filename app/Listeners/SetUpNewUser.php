<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\CreditService;

class SetUpNewUser
{
    public function __construct(private readonly CreditService $creditService) {}

    public function __invoke(User $user): void
    {
        $workspace = $user->ensureDefaultWorkspace();

        $starterCents = (int) config('services.credits.starter_amount_cents', 100);
        if ($starterCents > 0) {
            $this->creditService->topUp($workspace, $starterCents, 'Startguthaben');
        }
    }
}
