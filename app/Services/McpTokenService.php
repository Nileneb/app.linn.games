<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class McpTokenService
{
    public function createWorkerToken(User $user, int $phaseNr): NewAccessToken
    {
        return $user->createToken(
            "mcp-worker-p{$phaseNr}-".now()->timestamp,
            ['paper-search:worker']
        );
    }

    public function writeTempMcpConfig(string $plainTextToken): string
    {
        $path = sys_get_temp_dir().'/mcp-'.Str::uuid().'.json';
        $baseUrl = rtrim(config('services.paper_search.mcp_url', 'http://mcp-paper-search:8089/mcp'), '/');
        $config = [
            'mcpServers' => [
                'paper-search' => [
                    'type' => 'http',
                    'url' => "{$baseUrl}?token={$plainTextToken}",
                ],
            ],
        ];
        file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    public function cleanup(int $tokenId, ?string $configPath): void
    {
        PersonalAccessToken::find($tokenId)?->delete();

        if ($configPath && file_exists($configPath)) {
            unlink($configPath);
        }
    }
}
