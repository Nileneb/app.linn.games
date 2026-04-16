<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Beschränkt den Zugriff auf MCP-SSE-Streaming-Endpoints auf interne/lokale IPs.
 *
 * Wird via MCP_RESTRICT_TO_INTERNAL=true aktiviert (Standard in Production).
 * Erlaubt: localhost (127.0.0.1, ::1) sowie private Subnets (RFC 1918 / RFC 4193).
 * Externe Aufrufe werden mit 403 Forbidden abgewiesen.
 */
class AllowInternalMcpOnly
{
    /**
     * Private IP-Bereiche nach RFC 1918 und Loopback-Adressen.
     * Docker-interne Netzwerke fallen typischerweise in 172.16–31.x oder 10.x.
     */
    private const ALLOWED_CIDR_RANGES = [
        '127.0.0.0/8',      // IPv4 loopback
        '10.0.0.0/8',       // RFC 1918 Class A
        '172.16.0.0/12',    // RFC 1918 Class B (172.16.x.x – 172.31.x.x)
        '192.168.0.0/16',   // RFC 1918 Class C
    ];

    private const ALLOWED_IPV6 = [
        '::1',              // IPv6 loopback
        'fc00::/7',         // IPv6 unique-local (ULA)
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Restriction nur aktiv wenn MCP_RESTRICT_TO_INTERNAL=true (Standard: true)
        $restrictToInternal = (bool) config('services.mcp.restrict_to_internal', true);

        if (! $restrictToInternal) {
            return $next($request);
        }

        $ip = $request->ip();

        if (! $this->isInternalIp($ip)) {
            abort(Response::HTTP_FORBIDDEN, 'MCP streaming endpoint is restricted to internal access only.');
        }

        return $next($request);
    }

    /**
     * Prüft ob die IP-Adresse einer internen/lokalen IP entspricht.
     */
    private function isInternalIp(?string $ip): bool
    {
        if ($ip === null) {
            return false;
        }

        // IPv6 loopback direkt prüfen
        if ($ip === '::1') {
            return true;
        }

        // IPv6 unique-local (fc00::/7) — deckt fc00:: bis fdff::
        if (str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) {
            return true;
        }

        // IPv4-Bereiche via CIDR-Prüfung
        foreach (self::ALLOWED_CIDR_RANGES as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft ob eine IPv4-Adresse in einem CIDR-Block liegt.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $prefixLength] = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = ~((1 << (32 - (int) $prefixLength)) - 1);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
