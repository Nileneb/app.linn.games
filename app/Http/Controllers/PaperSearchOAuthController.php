<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class PaperSearchOAuthController extends Controller
{
    public function authorize(Request $request)
    {
        $redirectUri         = $request->query('redirect_uri');
        $state               = $request->query('state');
        $codeChallenge       = $request->query('code_challenge');
        $codeChallengeMethod = $request->query('code_challenge_method', 'S256');

        if (! $redirectUri || ! $codeChallenge) {
            abort(400, 'Missing redirect_uri or code_challenge');
        }

        $user = $request->user();

        if ($user->status !== 'active') {
            return redirect()->route('pending-approval');
        }

        $user->tokens()->where('name', 'Paper Search MCP')->delete();
        $tokenResult    = $user->createToken('Paper Search MCP', ['paper-search:read']);
        $plainTextToken = $tokenResult->plainTextToken;

        $code = Str::random(64);
        Redis::setex("paper_search_oauth:{$code}", 300, json_encode([
            'token'                 => $plainTextToken,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'redirect_uri'          => $redirectUri,
        ]));

        $callbackUrl = $redirectUri
            .(str_contains($redirectUri, '?') ? '&' : '?')
            .http_build_query(['code' => $code, 'state' => $state]);

        return redirect()->away($callbackUrl);
    }

    public function token(Request $request)
    {
        $code         = $request->input('code');
        $codeVerifier = $request->input('code_verifier');
        $redirectUri  = $request->input('redirect_uri');

        $stored = Redis::get("paper_search_oauth:{$code}");
        if (! $stored) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }

        $data = json_decode($stored, true);

        $expected = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        if (! hash_equals($data['code_challenge'], $expected)) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }

        if ($data['redirect_uri'] !== $redirectUri) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }

        Redis::del("paper_search_oauth:{$code}");

        return response()->json([
            'access_token' => $data['token'],
            'token_type'   => 'Bearer',
        ]);
    }
}
