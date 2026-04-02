<?php

namespace App\Providers;

use App\Models\Recherche\Projekt;
use App\Policies\ProjektPolicy;
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
        Gate::policy(Projekt::class, ProjektPolicy::class);

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
