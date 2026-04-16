<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class CleanupStaleWorkerTokens extends Command
{
    protected $signature = 'mcp:cleanup-stale-tokens';

    protected $description = 'Delete ephemeral MCP worker tokens older than 1 hour';

    public function handle(): int
    {
        $deleted = PersonalAccessToken::where('name', 'like', 'mcp-worker-%')
            ->where('created_at', '<', now()->subHour())
            ->delete();

        if ($deleted > 0) {
            $this->info("Deleted {$deleted} stale worker token(s).");
        }

        return self::SUCCESS;
    }
}
