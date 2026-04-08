<?php

namespace App\Http\Controllers;

use App\Services\StreamingAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SSE streaming endpoint for the dashboard chat (web-authenticated).
 *
 * Route: POST /chat/stream  (auth + verified middleware)
 * Called from big-research-chat Volt component via fetch() ReadableStream.
 */
class ChatStreamController extends Controller
{
    public function __invoke(Request $request, StreamingAgentService $streamingService): StreamedResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $agentId = config('services.langdock.dashboard_agent_id')
            ?? config('services.langdock.agent_id');

        abort_if(! $agentId, 503, 'Agent nicht konfiguriert.');

        return $streamingService->stream(
            agentId: $agentId,
            messages: [
                ['role' => 'user', 'content' => $request->string('message')->toString()],
            ],
            timeout: 120,
            context: [
                'workspace_id' => Auth::user()?->activeWorkspaceId(),
                'user_id'      => Auth::id(),
                'user_name'    => Auth::user()?->name,
                'source'       => 'dashboard_chat',
            ],
        );
    }
}
