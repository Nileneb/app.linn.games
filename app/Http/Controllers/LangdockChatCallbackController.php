<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LangdockChatCallbackController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'execution_id' => ['required', 'string', 'max:100'],
            'output'       => ['nullable', 'string'],
            'result'       => ['nullable', 'string'],
            'success'      => ['nullable', 'boolean'],
        ]);

        $executionId = $data['execution_id'];
        $content = $data['output'] ?? $data['result'] ?? null;

        $message = ChatMessage::where('langdock_execution_id', $executionId)
            ->where('role', 'assistant')
            ->whereNull('content')
            ->first();

        if (! $message) {
            Log::warning('Chat callback: no pending message found', [
                'execution_id' => $executionId,
            ]);

            return response()->json(['error' => 'No pending message found'], 404);
        }

        $message->update([
            'content' => $content ?: __('Keine Antwort erhalten.'),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
