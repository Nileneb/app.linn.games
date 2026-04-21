<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

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

    /**
     * Decode and validate an RS256 JWT issued by this service.
     *
     * Validates signature, issuer, and audience. Expiration is handled by firebase/php-jwt.
     *
     * @return array<string, mixed> Claims as associative array.
     *
     * @throws InvalidArgumentException On any signature, issuer, audience, or key-config failure.
     */
    public static function decodeAndValidate(string $token): array
    {
        $publicKey = self::resolvePublicKey();

        try {
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));
        } catch (Throwable $e) {
            throw new InvalidArgumentException('JWT decode failed: '.$e->getMessage(), 0, $e);
        }

        $claims = (array) json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true);

        if (($claims['iss'] ?? null) !== config('services.jwt.issuer')) {
            throw new InvalidArgumentException('Invalid JWT issuer.');
        }
        if (($claims['aud'] ?? null) !== config('services.jwt.audience')) {
            throw new InvalidArgumentException('Invalid JWT audience.');
        }

        return $claims;
    }

    private static function resolvePublicKey(): string
    {
        $raw = (string) config('services.jwt.public_key', '');
        if ($raw === '') {
            throw new InvalidArgumentException('JWT_PUBLIC_KEY is not configured.');
        }

        if (str_contains($raw, 'BEGIN')) {
            return $raw;
        }

        $decoded = base64_decode($raw, true);
        if ($decoded === false || ! str_contains($decoded, 'BEGIN')) {
            throw new InvalidArgumentException('JWT_PUBLIC_KEY is neither PEM nor valid base64-encoded PEM.');
        }

        return $decoded;
    }
}
