<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatResponseReady implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $workspaceId,
        public readonly int    $userId,
        public readonly string $messageId,
        public readonly string $content,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat.{$this->workspaceId}.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'chat.response';
    }
}
