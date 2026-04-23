<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\McpTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class McpHealthCommand extends Command
{
    protected $signature = 'mcp:health {service=paper-search : Service to check (paper-search)}';

    protected $description = 'Test MCP service connectivity and token auth from within the container';

    public function handle(McpTokenService $mcpTokenService): int
    {
        $service = $this->argument('service');

        if ($service !== 'paper-search') {
            $this->error("Unknown service '{$service}'. Supported: paper-search");

            return self::FAILURE;
        }

        return $this->checkPaperSearch($mcpTokenService);
    }

    private function checkPaperSearch(McpTokenService $mcpTokenService): int
    {
        $baseUrl = rtrim(config('services.paper_search.url', 'http://mcp-paper-search:8089'), '/');
        $mcpUrl = rtrim(config('services.paper_search.mcp_url', 'http://mcp-paper-search:8089/mcp'), '/');

        $this->line('Checking paper-search MCP...');
        $this->line("  REST base URL : {$baseUrl}");
        $this->line("  MCP URL       : {$mcpUrl}");

        // Step 1: Plain HTTP reachability via REST /search endpoint (no auth needed for 400 response)
        $this->line("\n[1] HTTP reachability (GET /search?query=test)");
        try {
            $response = Http::timeout(5)->get("{$baseUrl}/search", ['query' => 'test']);
            $status = $response->status();

            if ($status === 401) {
                $this->warn('  → 401 Unauthorized — service reachable, auth needed');
            } elseif ($status < 500) {
                $this->info("  → HTTP {$status} — service reachable");
            } else {
                $this->error("  → HTTP {$status} — service error");
            }
        } catch (\Throwable $e) {
            $this->error("  → Connection failed: {$e->getMessage()}");
            $this->error('  Service is NOT reachable from this container.');
            Log::error('mcp:health paper-search connectivity failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        // Step 2: Token auth via a real Sanctum token
        $this->line("\n[2] Sanctum token auth (REST /search with worker token)");
        $user = User::first();

        if (! $user) {
            $this->warn('  → No users in DB — skipping token test');
        } else {
            $token = $mcpTokenService->createWorkerToken($user, 0);
            $plain = $token->plainTextToken;

            try {
                $response = Http::timeout(5)
                    ->withToken($plain)
                    ->get("{$baseUrl}/search", ['query' => 'test', 'max_results_per_source' => 1]);

                $status = $response->status();

                if ($status === 200) {
                    $count = count($response->json() ?? []);
                    $this->info("  → HTTP 200 — auth OK, {$count} results returned");
                } elseif ($status === 401) {
                    $this->error('  → 401 Unauthorized — Sanctum token validation failed');
                    $this->line('  Possible causes:');
                    $this->line("  - paper-search service can't reach Postgres DB");
                    $this->line('  - LARAVEL_DB_* env vars wrong in docker-compose.yml');
                } else {
                    $this->warn("  → HTTP {$status}: ".$response->body());
                }
            } catch (\Throwable $e) {
                $this->error("  → Request failed: {$e->getMessage()}");
            } finally {
                $mcpTokenService->cleanup($token->accessToken->id, null);
            }
        }

        // Step 3: MCP endpoint availability
        $this->line("\n[3] MCP endpoint (POST {$mcpUrl})");
        try {
            $response = Http::timeout(5)->post($mcpUrl, []);
            $status = $response->status();

            if (in_array($status, [400, 401, 405, 422])) {
                $this->info("  → HTTP {$status} — MCP endpoint reachable (expected non-200 for empty POST)");
            } elseif ($status === 200) {
                $this->info('  → HTTP 200 — MCP endpoint responded');
            } else {
                $this->warn("  → HTTP {$status}: ".mb_substr($response->body(), 0, 200));
            }
        } catch (\Throwable $e) {
            $this->error("  → MCP endpoint not reachable: {$e->getMessage()}");
        }

        $this->line('');

        return self::SUCCESS;
    }
}
