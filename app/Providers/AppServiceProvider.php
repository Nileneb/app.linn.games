<?php

namespace App\Providers;

use App\Models\Recherche\P5Treffer;
use App\Models\User;
use App\Observers\P5TrefferObserver;
use App\Services\CreditService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        P5Treffer::observe(P5TrefferObserver::class);

        User::created(static function (User $user): void {
            $workspace = $user->ensureDefaultWorkspace();

            $starterCents = (int) config('services.credits.starter_amount_cents', 100);
            if ($starterCents > 0) {
                app(CreditService::class)->topUp($workspace, $starterCents, 'Startguthaben');
            }
        });

        RateLimiter::for('mcp', function (Request $request) {
            $limit = config('services.mcp.rate_limit', 60);

            return Limit::perMinute($limit)->by($request->bearerToken() ?: $request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
