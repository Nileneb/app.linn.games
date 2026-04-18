<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentResultEmbedding extends Model
{
    use HasUuids;

    protected $table = 'agent_result_embeddings';

    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'projekt_id',
        'chunk_text',
        'source_file',
        'embedding',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForProjekt($query, string $projektId): mixed
    {
        return $query->where('projekt_id', $projektId);
    }

    public function scopeForUser($query, int $userId, string $workspaceId): mixed
    {
        return $query->where('user_id', $userId)->where('workspace_id', $workspaceId);
    }
}
