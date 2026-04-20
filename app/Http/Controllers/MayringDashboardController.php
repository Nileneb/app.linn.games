<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtIssuer;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class MayringDashboardController extends Controller
{
    public function __construct(private readonly JwtIssuer $jwtIssuer) {}

    public function redirect(Request $request)
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace();

        $jwt = $this->jwtIssuer->issueForUser($user, $workspace);

        $code = Str::uuid()->toString();
        Redis::setex("mayring_ui_code:{$code}", 60, $jwt);

        $url = rtrim(config('services.mayring.ui_url', 'https://mcp.linn.games/ui'), '/')
            . '/?code=' . $code;

        return redirect()->away($url);
    }

    public function exchangeCode(Request $request)
    {
        $code = (string) $request->query('code', '');

        if (!$code || !Str::isUuid($code)) {
            return response()->json(['error' => 'invalid code'], 400);
        }

        $key   = "mayring_ui_code:{$code}";
        $token = Redis::get($key);

        if (!$token) {
            return response()->json(['error' => 'code expired or already used'], 401);
        }

        Redis::del($key);

        return response()->json(['token' => $token]);
    }

    /**
     * POST /api/mayring/refresh-token
     *
     * Wird von MayringCoder (src/api/web_ui.refresh_jwt) aufgerufen wenn ein JWT
     * in Gradio eine 401 zurückgibt. Dual-Auth:
     *   1. Sanctum-Token (auth:sanctum) — falls MayringCoder per Sanctum authentifiziert
     *   2. Bestehender RS256-JWT als Bearer — akzeptiert wenn Signatur+iss+aud stimmen,
     *      auch wenn bereits abgelaufen (sliding session innerhalb grace period).
     *
     * Response: {"token": "<neuer RS256-JWT>"} oder 401
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $this->resolveUserForRefresh($request);
        if (! $user) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $workspace = $user->currentWorkspace();
        if (! $workspace->hasMayringAccess()) {
            return response()->json(['error' => 'subscription required'], 403);
        }

        $jwt = $this->jwtIssuer->issueForUser($user, $workspace);

        return response()->json(['token' => $jwt]);
    }

    private function resolveUserForRefresh(Request $request): ?User
    {
        // 1) Sanctum-Token?
        $sanctumUser = $request->user('sanctum');
        if ($sanctumUser instanceof User) {
            return $sanctumUser;
        }

        // 2) Bearer = RS256-JWT?
        $header = $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $token = substr($header, 7);

        $publicKey = (string) config('services.jwt.public_key', '');
        if ($publicKey === '') {
            return null;
        }
        if (! str_contains($publicKey, 'BEGIN')) {
            $decoded = base64_decode($publicKey, true);
            if ($decoded === false) return null;
            $publicKey = $decoded;
        }

        // Sliding session: accept tokens expired up to refresh grace period.
        // Older than grace = user must re-login via /mayring/dashboard.
        $gracePeriod = (int) config('services.jwt.refresh_grace_seconds', 7 * 24 * 3600);
        $originalLeeway = JWT::$leeway;
        JWT::$leeway = $gracePeriod;

        try {
            $claims = JWT::decode($token, new Key($publicKey, 'RS256'));
        } catch (\Throwable) {
            return null;
        } finally {
            JWT::$leeway = $originalLeeway;
        }

        if (($claims->iss ?? null) !== config('services.jwt.issuer')) return null;
        if (($claims->aud ?? null) !== config('services.jwt.audience')) return null;

        $userId = $claims->sub ?? null;
        return $userId ? User::find($userId) : null;
    }
}
