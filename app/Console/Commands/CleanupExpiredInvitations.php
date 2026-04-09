<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredInvitations extends Command
{
    protected $signature = 'invitations:cleanup';

    protected $description = 'Delete users with expired invitations (status=invited, older than 28 days)';

    public function handle(): void
    {
        $count = User::where('status', 'invited')
            ->where('invitation_expires_at', '<', now())
            ->count();

        User::where('status', 'invited')
            ->where('invitation_expires_at', '<', now())
            ->delete();

        $this->info("Deleted {$count} expired invitation(s).");
        Log::info("CleanupExpiredInvitations: deleted {$count} expired invited users.");
    }
}
