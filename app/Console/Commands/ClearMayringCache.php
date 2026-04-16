<?php

namespace App\Console\Commands;

use App\Services\MayringMcpClient;
use Illuminate\Console\Command;

class ClearMayringCache extends Command
{
    protected $signature = 'mayring:cache:clear {--source= : Source-ID zum gezielten Löschen}';

    protected $description = 'Mayring-Cache leeren (Ingest + Search)';

    public function handle(): int
    {
        $client = app(MayringMcpClient::class);

        if ($sourceId = $this->option('source')) {
            $deleted = $client->clearCacheForSource($sourceId);
            $this->info("Cache für Source \"{$sourceId}\" gelöscht ({$deleted} Keys).");
        } else {
            $deleted = $client->clearAllCache();
            $this->info("Gesamter Mayring-Cache gelöscht ({$deleted} Keys).");
        }

        return self::SUCCESS;
    }
}
