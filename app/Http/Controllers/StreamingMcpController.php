<?php

namespace App\Http\Controllers;

use App\Services\StreamingAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SSE controller for streaming agent responses character-by-character.
 *
 * Endpoint: POST /api/mcp/agent-call/stream
 * Response: Server-Sent Events (text/event-stream)
 */
class StreamingMcpController extends Controller
{
    public function call(Request $request, StreamingAgentService $streamingService)
    {
        $validated = $request->validate([
            'agent_id' => 'required|string',
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|string',
            'messages.*.content' => 'required|string',
            'context' => 'sometimes|array',
            'timeout' => 'sometimes|integer|min:1|max:300',
        ]);

        Log::info('Streaming agent request started', [
            'agent_id' => $validated['agent_id'],
            'message_count' => count($validated['messages']),
        ]);

        return $streamingService->stream(
            $validated['agent_id'],
            $validated['messages'],
            $validated['timeout'] ?? 120,
            $validated['context'] ?? [],
        );
    }
}
