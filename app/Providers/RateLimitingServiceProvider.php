<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('mcp', function (Request $request) {
            $limit = config('services.mcp.rate_limit', 60);

            return Limit::perMinute($limit)->by($request->bearerToken() ?: $request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            $limit = config('services.webhooks.rate_limit', 30);

            return Limit::perMinute($limit)->by($request->ip());
        });
    }
}
