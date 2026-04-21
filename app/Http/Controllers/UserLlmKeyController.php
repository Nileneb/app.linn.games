<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class UserLlmKeyController extends Controller
{
    /**
     * POST /api/mcp/user-llm-key
     *
     * Outer auth: MCP_SERVICE_TOKEN (via VerifyMcpToken in service_only mode).
     * Body: {"jwt": "<rs256-user-jwt>"} identifies the user whose key is requested.
     *
     * Security rationale: service-token alone isn't enough to exfiltrate keys —
     * a valid signed user-JWT must be presented too, and it must resolve to a
     * user that has a key stored. Expired/revoked JWTs are rejected.
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'jwt' => ['required', 'string'],
        ]);

        try {
            $claims = JwtIssuer::decodeAndValidate((string) $request->input('jwt'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'invalid user jwt', 'detail' => $e->getMessage()], 401);
        }

        $sub = $claims['sub'] ?? null;
        if (! $sub) {
            return response()->json(['error' => 'jwt missing sub claim'], 401);
        }

        $user = User::find($sub);
        if (! $user) {
            return response()->json(['error' => 'user not found'], 404);
        }

        $apiKey = $user->llm_api_key;
        if (! $apiKey) {
            return response()->json(['error' => 'no api_key configured'], 404);
        }

        return response()->json([
            'api_key' => $apiKey,
            'provider' => $user->llm_provider_type ?: 'platform',
        ]);
    }
}
