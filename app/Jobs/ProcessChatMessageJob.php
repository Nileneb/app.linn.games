<?php

namespace App\Jobs;

use App\Actions\SendAgentMessage;
use App\Events\ChatResponseReady;
use App\Models\ChatMessage;
use App\Services\ChatTriggerwordRouter;
use App\Services\LangdockArtifactService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessChatMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Default dashboard chat agent config key — MUST match services.langdock.{KEY} in config/services.php
    private const DEFAULT_AGENT_CONFIG_KEY = 'agent_id';

    public int $tries   = 1;
    public int $timeout = 60;

    public function __construct(
        private readonly string $userMessageId,
        private readonly string $workspaceId,
        private readonly int    $userId,
        private readonly array  $context,
    ) {}

    public function handle(): void
    {
        $userMessage = ChatMessage::find($this->userMessageId);

        if ($userMessage === null) {
            return;
        }

        $route = app(ChatTriggerwordRouter::class)->route((string) $userMessage->content);

        $history = ChatMessage::historyFor($this->workspaceId, $this->userId);
        if ($history !== []) {
            $lastIndex = array_key_last($history);
            if ($lastIndex !== null && ($history[$lastIndex]['role'] ?? null) === 'user') {
                $history[$lastIndex]['content'] = $route['cleaned_message'];
            }
        }

        $context = $this->context + array_filter([
            'projekt_id' => $route['projekt_id'],
            'triggerword' => $route['triggerword'],
            'structured_output' => $route['structured_output'],
        ], static fn ($v) => $v !== null && $v !== '');

        $configKey = $route['config_key'] ?: self::DEFAULT_AGENT_CONFIG_KEY;

        $result = app(SendAgentMessage::class)->execute($configKey, $history, 60, $context);

        $artifact = app(LangdockArtifactService::class)->persistFromAgentResponse(
            (string) $result['content'],
            $context,
            [
                'scope' => 'chat',
                'config_key' => $configKey,
                'basename' => 'chat-' . $this->userMessageId,
                // For explicit "report"/synthesis calls we always want a .md file.
                'always_write_md' => $configKey === 'synthesis_agent' || ($route['triggerword'] ?? null) === 'report',
            ],
        );

        $assistantMsg = ChatMessage::saveAssistantReply($this->workspaceId, $this->userId, $artifact['display_content']);

        broadcast(new ChatResponseReady(
            workspaceId: $this->workspaceId,
            userId:      $this->userId,
            messageId:   $assistantMsg->id,
            content:     $artifact['display_content'],
        ));
    }
}
