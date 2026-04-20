<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VerifyMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            abort(401, 'Missing Bearer token.');
        }

        $token = substr($header, 7);

        $serviceToken = (string) config('services.mcp.service_token', '');
        if ($serviceToken !== '' && hash_equals($serviceToken, $token)) {
            $request->attributes->set('auth_mode', 'service');

            return $next($request);
        }

        $claims = $this->decodeJwt($token);

        if (($claims['iss'] ?? null) !== config('services.jwt.issuer')) {
            abort(401, 'Invalid token issuer.');
        }
        if (($claims['aud'] ?? null) !== config('services.jwt.audience')) {
            abort(401, 'Invalid token audience.');
        }

        $request->attributes->set('auth_mode', 'jwt');
        $request->attributes->set('jwt_subject', $claims['sub'] ?? null);
        $request->attributes->set('jwt_workspace_id', $claims['workspace_id'] ?? null);
        $request->attributes->set('jwt_scope', $claims['scope'] ?? []);

        if (! empty($claims['workspace_id']) && DB::getDriverName() === 'pgsql') {
            DB::select('SELECT set_config(?, ?, false)', ['app.current_workspace_id', (string) $claims['workspace_id']]);
        }

        return $next($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwt(string $token): array
    {
        $publicKey = (string) config('services.jwt.public_key', '');
        if ($publicKey === '') {
            abort(401, 'Invalid token.');
        }

        if (! str_contains($publicKey, 'BEGIN')) {
            $decoded = base64_decode($publicKey, true);
            if ($decoded === false || ! str_contains($decoded, 'BEGIN')) {
                abort(401, 'Invalid token.');
            }
            $publicKey = $decoded;
        }

        try {
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));
        } catch (Throwable $e) {
            abort(401, 'Invalid token: '.$e->getMessage());
        }

        return (array) json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true);
    }
}
