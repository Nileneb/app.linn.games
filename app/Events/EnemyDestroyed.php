<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnemyDestroyed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $enemyId,
        public readonly int $byUserId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel("game.{$this->code}")];
    }

    public function broadcastAs(): string
    {
        return 'enemy.destroyed';
    }
}
