<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyLangdockSignature
{
    private const TIMESTAMP_TOLERANCE = 300; // 5 minutes
    private const NONCE_TTL = 86_400; // 24 hours

    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.langdock.secret');

        if (! $secret) {
            abort(500, 'Langdock webhook secret not configured.');
        }

        $signature = $request->header('X-Langdock-Signature');

        if (! $signature) {
            abort(403, 'Missing signature.');
        }

        // Replay protection: validate timestamp
        $timestamp = $request->header('X-Langdock-Timestamp');

        if (! $timestamp || ! is_numeric($timestamp)) {
            abort(403, 'Missing or invalid timestamp.');
        }

        if (abs(time() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            abort(403, 'Request timestamp too old.');
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            abort(403, 'Invalid signature.');
        }

        // Replay protection: reject already-seen signatures via cache nonce
        $nonceKey = 'langdock_nonce:' . $signature;

        if (Cache::has($nonceKey)) {
            abort(403, 'Duplicate request rejected.');
        }

        Cache::put($nonceKey, true, self::NONCE_TTL);

        return $next($request);
    }
}
