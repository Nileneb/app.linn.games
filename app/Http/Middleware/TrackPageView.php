<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    private const BOT_PATTERNS = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'lighthouse', 'pagespeed',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && !$this->isBot($request->userAgent() ?? '')) {
            PageView::create([
                'path' => $request->path(),
                'ip_anonymous' => $this->anonymizeIp($request->ip()),
                'user_agent' => mb_substr($request->userAgent() ?? '', 0, 500),
            ]);
        }

        return $response;
    }

    private function anonymizeIp(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        if (str_contains($ip, ':')) {
            // IPv6: zero out last 80 bits
            return preg_replace('/:[0-9a-f]{0,4}:[0-9a-f]{0,4}:[0-9a-f]{0,4}:[0-9a-f]{0,4}:[0-9a-f]{0,4}$/i', ':0:0:0:0:0', $ip);
        }

        // IPv4: zero out last octet
        return preg_replace('/\.\d{1,3}$/', '.0', $ip);
    }

    private function isBot(string $userAgent): bool
    {
        $ua = strtolower($userAgent);

        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
