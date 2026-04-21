<?php

namespace App\Http\Middleware;

use App\Services\JwtIssuer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class VerifyMcpToken
{
    /**
     * @param  string  $mode  'dual' (service-token OR user-JWT) | 'service_only' (no user-JWT accepted)
     */
    public function handle(Request $request, Closure $next, string $mode = 'dual'): Response
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

        if ($mode === 'service_only') {
            abort(401, 'Service token required.');
        }

        try {
            $claims = JwtIssuer::decodeAndValidate($token);
        } catch (InvalidArgumentException $e) {
            abort(401, $e->getMessage());
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
}
