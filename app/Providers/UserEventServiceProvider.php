<?php

namespace App\Providers;

use App\Models\User;
use App\Services\CreditService;
use Illuminate\Support\ServiceProvider;

class UserEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        User::created(static function (User $user): void {
            $workspace = $user->ensureDefaultWorkspace();

            $starterCents = (int) config('services.credits.starter_amount_cents', 100);
            if ($starterCents > 0) {
                app(CreditService::class)->topUp($workspace, $starterCents, 'Startguthaben');
            }
        });
    }
}
