<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\CreditService;
use Illuminate\Support\Facades\Log;

class SetUpNewUser
{
    public function __construct(private readonly CreditService $creditService) {}

    public function __invoke(User $user): void
    {
        try {
            $workspace = $user->ensureDefaultWorkspace();

            $starterCents = (int) config('services.credits.starter_amount_cents', 100);
            if ($starterCents > 0) {
                $this->creditService->topUp($workspace, $starterCents, 'Startguthaben');
            }
        } catch (\Throwable $e) {
            Log::error('User-Initialisierung fehlgeschlagen', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
