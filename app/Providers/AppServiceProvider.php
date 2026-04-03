<?php

namespace App\Providers;

use App\Models\Recherche\P5Treffer;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Observers\P5TrefferObserver;
use App\Policies\ProjektPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        // Manuelle Registrierung nötig: Laravels Auto-Discovery würde App\Policies\Recherche\ProjektPolicy
        // erwarten, die Policy liegt aber in App\Policies\ProjektPolicy (bewusst flach gehalten).
        // Neue Policies entweder hier eintragen oder in App\Policies\ nach Laravel-Konvention benennen.
        Gate::policy(Projekt::class, ProjektPolicy::class);
        Gate::policy(Workspace::class, WorkspacePolicy::class);

        P5Treffer::observe(P5TrefferObserver::class);

        User::created(static function (User $user): void {
            $user->ensureDefaultWorkspace();
        });

        RateLimiter::for('mcp', function (Request $request) {
            return Limit::perMinute(60)->by($request->bearerToken() ?: $request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
