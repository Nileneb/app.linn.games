<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyLangdockSignature
{
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

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            abort(403, 'Invalid signature.');
        }

        return $next($request);
    }
}
