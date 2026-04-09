<?php

namespace App\Services;

use App\Models\ChatMessage;
use Illuminate\Support\Collection;

/**
 * Handles chat message persistence for the dashboard chat.
 * Extracted from big-research-chat Volt component to separate DB access from UI logic.
 */
class ChatService
{
    public function getMessages(string $workspaceId, int $userId, int $limit = 50): Collection
    {
        return ChatMessage::where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    public function saveUserMessage(string $workspaceId, int $userId, string $content): ChatMessage
    {
        return ChatMessage::create([
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'role' => 'user',
            'content' => $content,
        ]);
    }

    public function hasResponseAfter(ChatMessage $userMsg, int $userId): bool
    {
        return ChatMessage::where('workspace_id', $userMsg->workspace_id)
            ->where('user_id', $userId)
            ->where('role', 'assistant')
            ->where('created_at', '>=', $userMsg->created_at)
            ->exists();
    }

    public function clearMessages(string $workspaceId, int $userId): void
    {
        ChatMessage::where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->delete();
    }

    public function saveAssistantMessage(
        string $workspaceId,
        int $userId,
        string $content,
        ?string $relatedUserMsgId = null,
    ): ChatMessage {
        return ChatMessage::create([
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'role' => 'assistant',
            'content' => $content,
        ]);
    }

    public function updateLastAssistantMessage(
        string $workspaceId,
        int $userId,
        string $content,
        ?string $projectId = null,
        ?int $phaseNumber = null,
        ?string $agentName = null,
    ): void {
        // Save to file if project context is provided
        $filePath = null;
        if ($projectId !== null && $phaseNumber !== null) {
            $filePath = app(AgentResultStorageService::class)->saveResult(
                $workspaceId,
                $userId,
                $projectId,
                $phaseNumber,
                ['content' => $content],
                $agentName,
            );
        }

        ChatMessage::where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->where('role', 'assistant')
            ->orderByDesc('created_at')
            ->limit(1)
            ->update([
                'content' => $content,
                'agent_result_file_path' => $filePath,
            ]);
    }
}
