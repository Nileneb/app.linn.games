<?php

use App\Events\ChatResponseReady;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Str;

test('ChatResponseReady broadcasted auf privatem user-channel', function () {
    $workspaceId = (string) Str::uuid();
    $userId      = 42;
    $messageId   = (string) Str::uuid();
    $content     = 'KI-Antwort auf deine Frage';

    $event = new ChatResponseReady(
        workspaceId: $workspaceId,
        userId:      $userId,
        messageId:   $messageId,
        content:     $content,
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe("private-chat.{$workspaceId}.{$userId}");
});

test('ChatResponseReady hat korrekte Properties', function () {
    $workspaceId = 'ws-123';
    $userId      = 7;
    $messageId   = 'msg-456';
    $content     = 'Test-Antwort';

    $event = new ChatResponseReady(
        workspaceId: $workspaceId,
        userId:      $userId,
        messageId:   $messageId,
        content:     $content,
    );

    expect($event->workspaceId)->toBe($workspaceId);
    expect($event->userId)->toBe($userId);
    expect($event->messageId)->toBe($messageId);
    expect($event->content)->toBe($content);
});

test('ChatResponseReady broadcastAs gibt chat.response zurück', function () {
    $event = new ChatResponseReady('ws', 1, 'msg', 'content');

    expect($event->broadcastAs())->toBe('chat.response');
});
