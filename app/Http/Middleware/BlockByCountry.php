<?php

namespace App\Http\Middleware;

use App\Models\RegistrationAttempt;
use App\Services\GeoIpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BlockByCountry
{
    public function __construct(private readonly GeoIpService $geoIp) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Nur auf Registrierungs-Endpunkte anwenden
        if (! $request->is('register')) {
            return $next($request);
        }

        if (! config('security.geoblocking_enabled', false)) {
            return $next($request);
        }

        $blockedCountries = array_map(
            fn (string $code) => strtoupper(trim($code)),
            config('security.blocked_countries', [])
        );

        if (empty($blockedCountries)) {
            return $next($request);
        }

        $ip = $request->ip() ?? '';
        $geo = $this->geoIp->lookup($ip);
        $countryCode = strtoupper($geo['country_code'] ?? '');

        if ($countryCode && in_array($countryCode, $blockedCountries, true)) {
            RegistrationAttempt::create([
                'id' => Str::uuid(),
                'ip' => $ip,
                'user_agent' => $request->userAgent() ? mb_substr($request->userAgent(), 0, 512) : null,
                'reason' => 'geoblocking',
                'email' => null,
                'country_code' => $geo['country_code'] ?? null,
                'country_name' => $geo['country_name'] ?? null,
                'city' => $geo['city'] ?? null,
                'created_at' => now(),
            ]);

            abort(403, 'Zugriff aus dieser Region nicht erlaubt.');
        }

        return $next($request);
    }
}
