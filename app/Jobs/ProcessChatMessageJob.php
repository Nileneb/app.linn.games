<?php

namespace App\Jobs;

use App\Actions\SendAgentMessage;
use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessChatMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Dashboard chat agent config key — MUST match services.langdock.{KEY} in config/services.php
    private const AGENT_CONFIG_KEY = 'agent_id';

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

        $history = ChatMessage::where('workspace_id', $this->workspaceId)
            ->where('user_id', $this->userId)
            ->orderBy('created_at')
            ->limit(50)
            ->get()
            ->filter(fn (ChatMessage $m) => $m->content !== null)
            ->take(-20)
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->toArray();

        $result = app(SendAgentMessage::class)->execute(self::AGENT_CONFIG_KEY, $history, 60, $this->context);

        ChatMessage::create([
            'user_id'      => $this->userId,
            'workspace_id' => $this->workspaceId,
            'role'         => 'assistant',
            'content'      => $result['content'],
        ]);
    }
}
