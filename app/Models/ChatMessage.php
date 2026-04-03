<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasUuids;

    protected $table = 'chat_messages';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'role',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * Loads the recent conversation history for a given workspace/user as a plain role+content array,
     * ready to be passed to the agent API.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public static function historyFor(string $workspaceId, int $userId, int $limit = 20): array
    {
        return static::where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->limit(50)
            ->get()
            ->filter(fn (self $m) => $m->content !== null)
            ->take(-$limit)
            ->map(fn (self $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->toArray();
    }

    /**
     * Persists an assistant reply message for the given workspace/user.
     */
    public static function saveAssistantReply(string $workspaceId, int $userId, string $content): static
    {
        return static::create([
            'user_id'      => $userId,
            'workspace_id' => $workspaceId,
            'role'         => 'assistant',
            'content'      => $content,
        ]);
    }
}
