<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add security headers to MCP API responses.
 *
 * Protects against CSRF, XSS, and other common web vulnerabilities.
 */
class SecureMcpHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Content Security Policy - restrict to same origin + Claude API
        $response->header(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; connect-src 'self' https://api.anthropic.com;",
        );

        // Prevent MIME-sniffing
        $response->header('X-Content-Type-Options', 'nosniff');

        // Clickjacking protection
        $response->header('X-Frame-Options', 'DENY');

        // XSS protection
        $response->header('X-XSS-Protection', '1; mode=block');

        // Referrer Policy
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Feature Policy (Permissions Policy)
        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
