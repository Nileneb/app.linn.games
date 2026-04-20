<?php

namespace App\Http\Controllers;

use App\Models\LlmEndpoint;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Service-Endpoint: /api/mcp-service/llm-endpoint/{workspace_id}?agent=<key>
 *
 * Wird von MayringCoder (src/llm/endpoint.py::fetch_endpoint) aufgerufen,
 * um die Workspace-spezifische LLM-Backend-Config zu holen. Auth über
 * VerifyMcpToken-Middleware (MCP_SERVICE_TOKEN, server-to-server).
 *
 * Response-Format (siehe Spec):
 *  - Konfiguriert: {"provider": "ollama", "base_url": "http://...", "model": "...",
 *                    "api_key": "...", "extra": {...}}
 *  - Kein Endpoint: {"provider": "platform"}  (MayringCoder fällt auf Default zurück)
 *  - Unbekannte workspace_id: 404
 */
class LlmEndpointController extends Controller
{
    public function show(Request $request, string $workspaceId): JsonResponse
    {
        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            return response()->json(['error' => 'not found'], 404);
        }

        $agentKey = $request->query('agent');
        $endpoint = LlmEndpoint::resolveFor($workspace, is_string($agentKey) ? $agentKey : null);

        if (! $endpoint || $endpoint->provider === 'platform') {
            return response()->json(['provider' => 'platform']);
        }

        return response()->json([
            'provider' => $endpoint->provider,
            'base_url' => $endpoint->base_url,
            'model' => $endpoint->model,
            'api_key' => $endpoint->api_key, // decrypted via accessor
            'extra' => $endpoint->extra ?? new \stdClass,
        ]);
    }
}
