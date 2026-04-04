<?php

namespace App\Services;

use App\Models\Workspace;
use App\Services\CreditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LangdockAgentService
{
    public function __construct(
        private readonly LangdockContextInjector $contextInjector,
    ) {}

    /**
     * Ruft einen Langdock-Agenten synchron über die Agents Chat Completions API auf.
     *
     * Endpoint: POST https://api.langdock.com/agent/v1/chat/completions
     * Body: { agentId, messages: [{id, role, parts: [{type: "text", text}]}] }
     *
     * @param  string  $agentId  UUID des Langdock-Agenten
     * @param  array<int, array{role: string, content: string}>  $messages  Nachrichtenverlauf
     * @param  int  $timeout  HTTP-Timeout in Sekunden
     * @return array{content: string, raw: array}
     *
     * @throws \App\Services\LangdockAgentException
     */
    public function call(string $agentId, array $messages, int $timeout = 120, array $context = []): array
    {
        $apiKey  = config('services.langdock.api_key');
        $baseUrl = config('services.langdock.base_url');
        $logContext = $this->buildLogContext($agentId, $messages, $timeout, $context);

        if (! $apiKey || ! $agentId) {
            Log::error('Langdock agent configuration missing', $logContext + [
                'has_api_key' => (bool) $apiKey,
            ]);

            throw new LangdockAgentException('Langdock API-Key oder Agent-ID nicht konfiguriert.');
        }

        $transformedMessages = array_map(fn (array $msg) => [
            'id'    => (string) Str::uuid(),
            'role'  => $msg['role'],
            'parts' => [['type' => 'text', 'text' => $msg['content']]],
        ], $messages);

        $transformedMessages = $this->contextInjector->inject($transformedMessages, $context);

        $metadata = array_filter([
            'projekt_id' => $context['projekt_id'] ?? null,
            'user_id'    => $context['user_id'] ?? null,
        ]);

        $workspace = $this->resolveWorkspace($context);
        if ($workspace !== null) {
            app(CreditService::class)->assertHasBalance($workspace);
        }

        $startedAt = microtime(true);

        Log::info('Langdock agent request started', $logContext);

        $body = ['agentId' => $agentId, 'messages' => $transformedMessages];
        if ($metadata !== []) {
            $body['metadata'] = $metadata;
        }

        $response = $this->callWithRetry($body, $apiKey, $baseUrl, $timeout, $logContext, $startedAt);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $raw     = $response->json() ?? [];
        $content = $raw['messages'][0]['content']
            ?? $raw['result'][0]['content'][0]['text']
            ?? $response->body();

        $tokensUsed = $raw['usage']['total_tokens'] ?? $this->estimateTokens($transformedMessages);
        if ($workspace !== null && $tokensUsed > 0) {
            app(CreditService::class)->deduct($workspace, $tokensUsed, $context['config_key'] ?? 'unknown');
        }

        Log::info('Langdock agent request succeeded', $logContext + [
            'status'          => $response->status(),
            'duration_ms'     => $durationMs,
            'response_length' => mb_strlen((string) $content),
        ]);

        return [
            'content' => (string) $content,
            'raw'     => $raw,
        ];
    }

    /**
     * Ruft einen Agenten über seinen config-Key auf.
     * z.B. callByConfigKey('scoping_mapping_agent', $messages)
     */
    public function callByConfigKey(string $configKey, array $messages, int $timeout = 120, array $context = []): array
    {
        $agentId = config("services.langdock.{$configKey}");

        if (! $agentId) {
            throw new LangdockAgentException("Langdock Agent '{$configKey}' nicht in config/services.php konfiguriert.");
        }

        return $this->call($agentId, $messages, $timeout, $context + [
            'config_key' => $configKey,
        ]);
    }

    /**
     * Führt den HTTP-Request mit exponentiellem Backoff-Retry durch.
     *
     * Retries bei: HTTP 5xx, ConnectionException (Timeout, DNS-Fehler u. ä.).
     * Konfigurierbar über:
     *   services.langdock.retry_attempts  (Standard: 3)
     *   services.langdock.retry_sleep_ms  (Basis-Wartezeit in ms, Standard: 500)
     *
     * @throws LangdockAgentException
     */
    private function callWithRetry(
        array $body,
        string $apiKey,
        string $baseUrl,
        int $timeout,
        array $logContext,
        float $startedAt,
    ): \Illuminate\Http\Client\Response {
        $maxRetries  = (int) config('services.langdock.retry_attempts', 3);
        $retrySleepMs = (int) config('services.langdock.retry_sleep_ms', 500);

        $attempt = 0;

        while (true) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ])->timeout($timeout)->post($baseUrl, $body);

                if ($response->serverError() && $attempt < $maxRetries) {
                    $delayMs = $retrySleepMs * (2 ** $attempt);
                    Log::warning('Langdock server error, retrying', $logContext + [
                        'attempt'  => $attempt + 1,
                        'max'      => $maxRetries,
                        'delay_ms' => $delayMs,
                        'status'   => $response->status(),
                    ]);
                    usleep($delayMs * 1000);
                    $attempt++;
                    continue;
                }

                if ($response->failed()) {
                    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                    Log::error('Langdock agent request failed', $logContext + [
                        'status'           => $response->status(),
                        'duration_ms'      => $durationMs,
                        'response_excerpt' => Str::limit($response->body(), 1000),
                    ]);
                    throw new LangdockAgentException(
                        "Langdock API returned HTTP {$response->status()}",
                        $response->status(),
                    );
                }

                return $response;

            } catch (LangdockAgentException $e) {
                throw $e;
            } catch (\Throwable $e) {
                if ($attempt < $maxRetries) {
                    $delayMs = $retrySleepMs * (2 ** $attempt);
                    Log::warning('Langdock connection error, retrying', $logContext + [
                        'attempt'   => $attempt + 1,
                        'max'       => $maxRetries,
                        'delay_ms'  => $delayMs,
                        'exception' => $e::class,
                        'message'   => $e->getMessage(),
                    ]);
                    usleep($delayMs * 1000);
                    $attempt++;
                    continue;
                }

                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                Log::error('Langdock agent request crashed', $logContext + [
                    'duration_ms' => $durationMs,
                    'exception'   => $e::class,
                    'message'     => $e->getMessage(),
                ]);

                throw new LangdockAgentException(
                    'Verbindung zu Langdock fehlgeschlagen: ' . $e->getMessage(),
                    0,
                    $e,
                );
            }
        }
    }

    /**
     * Listet alle in Langdock vorhandenen Agenten auf.
     *
     * Endpoint: GET https://api.langdock.com/agent/v1/list
     * Antwort wird 5 Minuten gecacht.
     *
     * @return array<int, array{id: string, name: string, description: string|null, status: string|null}>
     *
     * @throws \App\Services\LangdockAgentException
     */
    public function listAgents(): array
    {
        $apiKey  = config('services.langdock.api_key');
        $listUrl = config('services.langdock.list_url');

        if (! $apiKey) {
            throw new LangdockAgentException('Langdock API-Key nicht konfiguriert.');
        }

        return Cache::remember('langdock.agents.list', 300, function () use ($apiKey, $listUrl) {
            Log::info('Langdock agent list requested', ['url' => $listUrl]);

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ])->timeout(15)->get($listUrl);

                if ($response->failed()) {
                    Log::error('Langdock agent list request failed', [
                        'status'           => $response->status(),
                        'response_excerpt' => Str::limit($response->body(), 500),
                    ]);

                    throw new LangdockAgentException(
                        "Langdock Agent-Liste: HTTP {$response->status()}",
                        $response->status(),
                    );
                }

                $raw    = $response->json() ?? [];
                $agents = $raw['agents'] ?? $raw['data'] ?? $raw;

                if (! is_array($agents)) {
                    Log::warning('Langdock agent list: unexpected response shape', ['raw' => $raw]);
                    return [];
                }

                Log::info('Langdock agent list fetched', ['count' => count($agents)]);

                return $agents;
            } catch (LangdockAgentException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::error('Langdock agent list crashed', [
                    'exception' => $e::class,
                    'message'   => $e->getMessage(),
                ]);

                throw new LangdockAgentException(
                    'Verbindung zu Langdock fehlgeschlagen: ' . $e->getMessage(),
                    0,
                    $e,
                );
            }
        });
    }

    /**
     * Gibt die lokal in config/services.php konfigurierten Agent-Keys und ihre UUIDs zurück.
     * Schließt Nicht-Agent-Keys aus.
     *
     * @return array<string, string>  Key => UUID
     */
    public function configuredAgents(): array
    {
        $skip = ['base_url', 'list_url', 'api_key', 'price_per_1k_tokens_cents', 'low_balance_threshold_percent', 'agent_daily_limits'];

        return collect(config('services.langdock', []))
            ->except($skip)
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->all();
    }

    private function resolveWorkspace(array $context): ?Workspace
    {
        $id = $context['workspace_id'] ?? null;
        return $id ? Workspace::find($id) : null;
    }

    /**
     * Schätzt die Token-Anzahl einer Nachrichtenliste.
     *
     * Genauer als chars/4, weil:
     * - Non-ASCII-Zeichen (CJK, Arabisch, Emoji) tokenisieren weniger effizient (~2 Zeichen/Token)
     * - Pro Nachricht werden ~4 Token Overhead für Role + Struktur addiert
     */
    private function estimateTokens(array $messages): int
    {
        $total = count($messages) * 4; // Overhead pro Nachricht (role, formatting)

        foreach ($messages as $m) {
            $text = $m['parts'][0]['text'] ?? '';
            $nonAsciiCount = (int) preg_match_all('/[^\x00-\x7F]/u', $text);
            $asciiCount    = mb_strlen($text) - $nonAsciiCount;
            $total += (int) ceil($asciiCount / 4) + (int) ceil($nonAsciiCount / 2);
        }

        return max(1, $total);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    private function buildLogContext(string $agentId, array $messages, int $timeout, array $context): array
    {
        $lastMessage = $messages === [] ? null : $messages[array_key_last($messages)];

        return array_filter($context + [
            'request_id'           => (string) Str::uuid(),
            'agent_id'             => $agentId,
            'message_count'        => count($messages),
            'timeout_seconds'      => $timeout,
            'last_message_role'    => $lastMessage['role'] ?? null,
            'last_message_preview' => isset($lastMessage['content'])
                ? Str::limit((string) preg_replace('/\s+/', ' ', $lastMessage['content']), 180)
                : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}

