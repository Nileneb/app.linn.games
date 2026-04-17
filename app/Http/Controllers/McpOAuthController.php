<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class McpOAuthController extends Controller
{
    public function authorize(Request $request): RedirectResponse
    {
        $redirectUri = $request->query('redirect_uri');
        $state = $request->query('state', '');
        $codeChallenge = $request->query('code_challenge');
        $codeChallengeMethod = $request->query('code_challenge_method', 'S256');

        if (! $redirectUri) {
            abort(400, 'redirect_uri required');
        }

        $user = $request->user();
        $workspace = $user->currentWorkspace();

        if (! $workspace->hasMayringAccess()) {
            return redirect()->route('mayring.subscribe')
                ->with('info', 'Mayring Memory erfordert ein aktives Abo.');
        }

        // Always create a fresh MCP token (old one may have rotated)
        $user->tokens()->where('name', 'MCP Claude Web')->delete();
        $tokenResult = $user->createToken('MCP Claude Web', ['mcp:memory']);
        $plainTextToken = $tokenResult->plainTextToken; // "{id}|{plaintext}"

        $code = Str::random(40);

        // Register auth code in the MCP server's in-memory store
        $mcpUrl = rtrim(config('services.mayring_mcp.endpoint', 'http://mayring-api:8090'), '/');
        // The MCP HTTP server runs separately from the API server
        $mcpHttpUrl = str_replace(':8090', ':8092', $mcpUrl);
        $mcpHttpUrl = str_replace('mayring-api', 'mayring-mcp', $mcpHttpUrl);

        Http::withToken(config('services.mayring_mcp.auth_token'))
            ->timeout(5)
            ->post("{$mcpHttpUrl}/authorize/register-code", [
                'code'                  => $code,
                'token'                 => $plainTextToken,
                'workspace_id'          => $workspace->id,
                'code_challenge'        => $codeChallenge,
                'code_challenge_method' => $codeChallengeMethod,
                'redirect_uri'          => $redirectUri,
                'state'                 => $state,
            ]);

        $callbackUrl = $redirectUri
            .(str_contains($redirectUri, '?') ? '&' : '?')
            .'code='.urlencode($code)
            .'&state='.urlencode($state);

        return redirect()->away($callbackUrl);
    }
}
