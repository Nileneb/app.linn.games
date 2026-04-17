<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class SyncTorNodes extends Command
{
    protected $signature = 'security:sync-tor-nodes';

    protected $description = 'Synchronisiert Tor-Exit-Node-Liste von torproject.org in Redis';

    public function handle(): int
    {
        $response = Http::timeout(30)->get('https://check.torproject.org/torbulkexitlist');

        if ($response->failed()) {
            $this->error("Tor-Node-Liste konnte nicht geladen werden: HTTP {$response->status()}");
            return self::FAILURE;
        }

        $ips = array_filter(
            array_map('trim', explode("\n", $response->body())),
            fn (string $line) => $line !== '' && !str_starts_with($line, '#')
        );

        if (empty($ips)) {
            $this->warn('Tor-Node-Liste ist leer — Abbruch ohne Redis-Update.');
            return self::FAILURE;
        }

        $key = 'security:tor_nodes';
        Redis::del($key);
        Redis::sadd($key, ...$ips);
        Redis::expire($key, 6 * 3600);

        $this->info(sprintf('Tor-Node-Liste synchronisiert: %d IPs importiert.', count($ips)));

        return self::SUCCESS;
    }
}
