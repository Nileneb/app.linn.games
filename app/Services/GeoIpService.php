<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoIpService
{
    /**
     * Resolve an IP address to geographic location data.
     * Results are cached for 24 hours to avoid redundant lookups.
     *
     * Returns array with keys: country_code, country_name, city
     * Returns empty array on failure or for private/local IPs.
     */
    public function lookup(string $ip): array
    {
        if ($this->isPrivateIp($ip)) {
            return [];
        }

        return Cache::remember("geoip:{$ip}", 86400, fn () => $this->fetchFromApi($ip));
    }

    private function fetchFromApi(string $ip): array
    {
        try {
            // ip-api.com: kostenlos, kein API-Key, max 45 req/min
            // Nur für Monitoring/Sicherheit genutzt — kein personenbezogener Kontext
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'countryCode,country,city,status',
                'lang' => 'de',
            ]);

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success') {
                return [];
            }

            return [
                'country_code' => $data['countryCode'] ?? null,
                'country_name' => $data['country'] ?? null,
                'city' => $data['city'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::debug('GeoIpService: Lookup fehlgeschlagen', ['ip' => $ip, 'error' => $e->getMessage()]);

            return [];
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
