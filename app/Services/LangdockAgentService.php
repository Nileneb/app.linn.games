<?php

namespace App\Services;

use App\Models\Workspace;
use App\Services\CreditService;
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

        try {
            $body = ['agentId' => $agentId, 'messages' => $transformedMessages];
            if ($metadata !== []) {
                $body['metadata'] = $metadata;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout($timeout)
                ->post($baseUrl, $body);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($response->failed()) {
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
        } catch (LangdockAgentException $e) {
            throw $e;
        } catch (\Throwable $e) {
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

    private function resolveWorkspace(array $context): ?Workspace
    {
        $id = $context['workspace_id'] ?? null;
        return $id ? Workspace::find($id) : null;
    }

    private function estimateTokens(array $messages): int
    {
        $chars = array_sum(array_map(
            fn (array $m) => mb_strlen($m['parts'][0]['text'] ?? ''),
            $messages,
        ));
        return (int) ceil($chars / 4);
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

