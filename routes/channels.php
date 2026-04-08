<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'chat.{workspaceId}.{userId}',
    function ($user, string $workspaceId, string $userId): bool {
        return (int) $user->id === (int) $userId
            && $user->activeWorkspaceId() === $workspaceId;
    }
);
