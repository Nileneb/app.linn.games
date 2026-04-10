<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameSessionUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly int $wave,
        public readonly bool $gameOver,
        public readonly array $leaderboard,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel("game.{$this->code}")];
    }

    public function broadcastAs(): string
    {
        return 'session.update';
    }
}
