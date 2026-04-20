<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;
use RuntimeException;

class JwtIssuer
{
    public function issueForUser(User $user, Workspace $workspace): string
    {
        $now = time();
        $payload = [
            'iss' => config('services.jwt.issuer'),
            'aud' => config('services.jwt.audience'),
            'sub' => (string) $user->id,
            'iat' => $now,
            'exp' => $now + (int) config('services.jwt.ttl', 28800),
            'jti' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'scope' => $this->scopesFor($user),
            'email' => $user->email,
        ];

        // Provider-Claims für MayringCoder (non-sensitive — api_key bleibt server-side,
        // wird via separatem Callback geholt).
        $providerConfig = $user->llmProviderConfig();
        $payload['llm_provider'] = $providerConfig['type'];
        if ($providerConfig['type'] !== 'platform') {
            $payload['llm_model'] = $providerConfig['model'] ?? null;
            if ($providerConfig['type'] === 'openai-compatible') {
                $payload['llm_endpoint'] = $providerConfig['endpoint'] ?? null;
                $payload['llm_requires_key'] = ! empty($providerConfig['api_key']);
            }
            if ($providerConfig['type'] === 'anthropic-byo') {
                $payload['llm_requires_key'] = true;
            }
        }

        return JWT::encode($payload, $this->privateKey(), 'RS256');
    }

    private function scopesFor(User $user): array
    {
        $scopes = ['mcp:memory'];
        if ($user->hasRole(UserRole::ADMIN)) {
            $scopes[] = 'admin';
        }

        return $scopes;
    }

    private function privateKey(): string
    {
        $raw = (string) config('services.jwt.private_key', '');
        if ($raw === '') {
            throw new RuntimeException('JWT_PRIVATE_KEY is not configured.');
        }

        if (str_contains($raw, 'BEGIN')) {
            return $raw;
        }

        $decoded = base64_decode($raw, true);
        if ($decoded === false || ! str_contains($decoded, 'BEGIN')) {
            throw new RuntimeException('JWT_PRIVATE_KEY is neither PEM nor valid base64-encoded PEM.');
        }

        return $decoded;
    }
}
