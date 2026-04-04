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

            return Limit::perMinute($limit)->by($this->resolveIdentifier($request));
        });
    }

    private function resolveIdentifier(Request $request): string
    {
        $token = trim((string) $request->bearerToken());

        return $token !== '' ? hash('sha256', $token) : ($request->ip() ?? 'unknown');
    }
}
