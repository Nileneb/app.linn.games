<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for resolving Open Access URLs via Unpaywall API.
 * 
 * Handles communication with Unpaywall API to find Open Access PDF URLs for academic papers.
 */
class UnpaywallService
{
    private const UNPAYWALL_BASE_URL = 'https://api.unpaywall.org/v2/';
    private const TIMEOUT = 10;

    /**
     * Resolve the Open Access PDF URL for a given DOI.
     *
     * @param string $doi The Digital Object Identifier
     * @return ?string The URL to the PDF if found, null otherwise
     */
    public function resolveOaUrl(string $doi): ?string
    {
        $email = config('mail.from.address', 'info@linn.games');

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->get(self::UNPAYWALL_BASE_URL . rawurlencode($doi), [
                    'email' => $email,
                ]);

            if ($response->failed()) {
                Log::warning('Unpaywall API request failed', [
                    'doi'    => $doi,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $url = $response->json('best_oa_location.url_for_pdf');

            if (blank($url)) {
                Log::info('No Open Access URL found in Unpaywall', [
                    'doi' => $doi,
                ]);
                return null;
            }

            return $url;
        } catch (\Throwable $e) {
            Log::error('Unpaywall API error', [
                'doi'     => $doi,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
