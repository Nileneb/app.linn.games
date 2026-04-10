<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'chat.{workspaceId}.{userId}',
    function ($user, string $workspaceId, string $userId): bool {
        return (int) $user->id === (int) $userId
            && $user->activeWorkspaceId() === $workspaceId;
    }
);

Broadcast::channel('game.{code}', function ($user, string $code) {
    $session = \App\Models\GameSession::where('code', $code)
        ->where('status', '!=', 'ended')
        ->first();

    if (! $session) {
        return false;
    }

    $inSession = \Illuminate\Support\Facades\DB::table('game_session_players')
        ->where('session_id', $session->id)
        ->where('user_id', $user->id)
        ->exists();

    return $inSession ? ['id' => $user->id, 'name' => $user->name] : false;
});
