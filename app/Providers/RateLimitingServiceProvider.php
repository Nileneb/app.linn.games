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
            $token = $request->bearerToken();
            $identifier = $token !== null && $token !== ''
                ? hash('sha256', $token)
                : $request->ip();

            return Limit::perMinute($limit)->by($identifier);
        });
    }
}
