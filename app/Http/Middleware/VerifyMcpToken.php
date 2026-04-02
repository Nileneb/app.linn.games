<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            abort(401, 'Missing Bearer token.');
        }

        $token = config('services.mcp.auth_token');

        if (! $token) {
            abort(500, 'MCP auth token not configured.');
        }

        if (! hash_equals($token, substr($header, 7))) {
            abort(401, 'Invalid token.');
        }

        return $next($request);
    }
}
