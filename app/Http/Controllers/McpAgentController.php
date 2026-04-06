<?php

namespace App\Http\Controllers;

use App\Services\AgentResultStorageService;
use App\Services\LangdockAgentException;
use App\Services\LangdockAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

            // Save response to file if project context provided
            $filePath = null;
            $context = $validated['context'] ?? [];
            if (isset($context['workspace_id'], $context['project_id'], $context['phase_number'])) {
                $userId = $context['user_id'] ?? Auth::id() ?? 0;
                $filePath = app(AgentResultStorageService::class)->saveResult(
                    $context['workspace_id'],
                    $userId,
                    $context['project_id'],
                    $context['phase_number'],
                    $response,
                    $context['agent_name'] ?? null,
                );
            }

            return response()->json([
                'success' => true,
                'content' => $response['content'],
                'raw' => $response['raw'],
                'stored_at' => $filePath,
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
