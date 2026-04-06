<?php

namespace App\Http\Controllers;

use App\Services\LangdockAgentException;
use App\Services\LangdockAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class McpAgentController extends Controller
{
    public function call(Request $request)
    {
        $validated = $request->validate([
            'agent_id' => 'required|string',
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|string',
            'messages.*.content' => 'required|string',
            'context' => 'sometimes|array',
            'timeout' => 'sometimes|integer|min:1|max:300',
        ]);

        try {
            $response = app(LangdockAgentService::class)->call(
                $validated['agent_id'],
                $validated['messages'],
                $validated['timeout'] ?? 120,
                $validated['context'] ?? [],
            );

            return response()->json([
                'success' => true,
                'content' => $response['content'],
                'raw' => $response['raw'],
            ]);
        } catch (LangdockAgentException $e) {
            Log::error('MCP agent call failed', [
                'agent_id' => $validated['agent_id'],
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $status = $e->getCode() >= 400 ? $e->getCode() : 500;

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $status);
        }
    }
}
