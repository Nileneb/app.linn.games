<?php

namespace App\Console\Commands;

use App\Models\PendingRegistration;
use Illuminate\Console\Command;

class PrunePendingRegistrations extends Command
{
    protected $signature = 'security:prune-pending-registrations';

    protected $description = 'Löscht abgelaufene PendingRegistration-Datensätze';

    public function handle(): int
    {
        $deleted = PendingRegistration::where('expires_at', '<', now())->delete();

        $this->info("Abgelaufene PendingRegistrations bereinigt: {$deleted} Datensätze gelöscht.");

        return self::SUCCESS;
    }
}
