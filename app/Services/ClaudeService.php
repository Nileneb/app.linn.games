<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly PromptLoaderService $promptLoader,
        private readonly ClaudeContextBuilder $contextBuilder,
        private readonly CreditService $creditService,
        private readonly MayringMcpClient $mayringClient,
        private readonly ClaudeRetryHandler $retryHandler,
        private readonly ClaudeToolUseService $toolUseService,
    ) {}

    /**
     * Ruft einen konfigurierten Claude-Agent synchron auf.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, raw: array, tokens_used: int}
     *
     * @throws ClaudeAgentException
     */
    public function callByConfigKey(string $configKey, array $messages, array $context = [], int $maxTokens = 0): array
    {
        $promptFile = config("services.anthropic.agents.{$configKey}");

        if (! $promptFile) {
            throw new ClaudeAgentException("Claude Agent '{$configKey}' nicht in config/services.anthropic.agents konfiguriert.");
        }

        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            throw new ClaudeAgentException('CLAUDE_API_KEY nicht konfiguriert.');
        }

        $systemPrompt = $this->promptLoader->buildSystemPrompt($promptFile);
        $contextBlock = $this->contextBuilder->build(array_merge(['config_key' => $configKey], $context));

        if ($contextBlock !== '') {
            $systemPrompt .= "\n\n".$contextBlock;
        }

        $model = $this->resolveModelForCall($configKey, $context);
        $maxTok = $maxTokens > 0 ? $maxTokens : (int) config('services.anthropic.max_tokens', 8192);
        $workspace = $this->resolveWorkspace($context);

        // Workspace hat eigenen LLM-Endpoint konfiguriert → kein Platform-Billing.
        // User zahlt direkt beim Provider. Siehe App\Models\LlmEndpoint::resolveFor.
        $userManagedEndpoint = $workspace
            ? \App\Models\LlmEndpoint::resolveFor($workspace, $configKey)
            : null;
        $isUserManaged = $userManagedEndpoint && $userManagedEndpoint->provider !== 'platform';

        if (! $isUserManaged && $workspace !== null) {
            $this->creditService->assertHasBalance($workspace);
        }

        $startedAt = microtime(true);

        if ($configKey === 'mayring_agent' && $workspace !== null && ! $workspace->hasMayringAccess()) {
            throw new ClaudeAgentException('Mayring-Agent erfordert aktives Abo. Bitte unter Einstellungen → Mayring-Abo abonnieren.');
        }

        // Mayring-Agent: Pi/Ollama-Pfad wenn konfiguriert (keine Anthropic-Kosten)
        if ($configKey === 'mayring_agent' && $this->useOllamaForWorkers()) {
            return $this->callMayringViaPi($systemPrompt, $messages, $configKey, $workspace);
        }

        // Mayring-Agent: Tool-Use-Loop mit Anthropic (mit Prompt-Cache)
        if ($configKey === 'mayring_agent') {
            return $this->toolUseService->callWithToolUse($apiKey, $model, $systemPrompt, $messages, $maxTok, $configKey, $workspace);
        }

        $response = $this->retryHandler->callWithRetry($apiKey, $model, $systemPrompt, $messages, $maxTok, $configKey);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::error('Claude API Fehler', [
                'config_key' => $configKey,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ClaudeAgentException("Claude API Fehler {$response->status()}: ".$response->body());
        }

        $raw = $response->json() ?? [];
        $content = $raw['content'][0]['text'] ?? '';
        $inputTokens = (int) ($raw['usage']['input_tokens'] ?? 0);
        $outputTokens = (int) ($raw['usage']['output_tokens'] ?? 0);
        $tokensUsed = $inputTokens + $outputTokens;

        if (! $isUserManaged && $workspace !== null && $tokensUsed > 0) {
            $this->creditService->deduct($workspace, $inputTokens, $configKey, $outputTokens);
        }

        Log::info('Claude agent request succeeded', [
            'config_key' => $configKey,
            'model' => $model,
            'duration_ms' => $durationMs,
            'tokens_used' => $tokensUsed,
        ]);

        return [
            'content' => $content,
            'raw' => $raw,
            'tokens_used' => $tokensUsed,
        ];
    }

    /**
     * Streamt eine Claude-Antwort Token für Token als Generator.
     *
     * Nutzt Anthropic Messages API mit `stream: true`. Jeder yielded Eintrag
     * ist ein SSE-Event: ['type' => 'content', 'text' => '...'] für Text-Deltas,
     * ['type' => 'done', 'input_tokens' => ..., 'output_tokens' => ...] am Ende.
     *
     * @return \Generator<int, array{type: string, text?: string, input_tokens?: int, output_tokens?: int}>
     */
    public function callStreaming(string $configKey, array $messages, array $context = [], int $maxTokens = 0): \Generator
    {
        $promptFile = config("services.anthropic.agents.{$configKey}");
        if (! $promptFile) {
            throw new ClaudeAgentException("Claude Agent '{$configKey}' nicht konfiguriert.");
        }

        $apiKey = config('services.anthropic.api_key');
        if (! $apiKey) {
            throw new ClaudeAgentException('CLAUDE_API_KEY nicht konfiguriert.');
        }

        $systemPrompt = $this->promptLoader->buildSystemPrompt($promptFile);
        $contextBlock = $this->contextBuilder->build(array_merge(['config_key' => $configKey], $context));
        if ($contextBlock !== '') {
            $systemPrompt .= "\n\n".$contextBlock;
        }

        $model = $this->resolveModelForCall($configKey, $context);
        $maxTok = $maxTokens > 0 ? $maxTokens : (int) config('services.anthropic.max_tokens', 8192);
        $workspace = $this->resolveWorkspace($context);

        if ($workspace !== null) {
            $this->creditService->assertHasBalance($workspace);
        }

        $attempts = (int) config('services.anthropic.retry_attempts', 3);
        $sleepMs = (int) config('services.anthropic.retry_sleep_ms', 500);
        $response = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'anthropic-beta' => 'prompt-caching-2024-07-31',
                'content-type' => 'application/json',
            ])->withOptions(['stream' => true])
              ->timeout(120)
              ->post(self::API_URL, [
                  'model' => $model,
                  'max_tokens' => $maxTok,
                  'stream' => true,
                  'system' => [['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']]],
                  'messages' => $messages,
              ]);

            if ($response->successful()) {
                break;
            }

            if ($response->status() === 429) {
                $retryAfter = (int) $response->header('Retry-After', (int) ceil($sleepMs / 1000));
                Log::warning("callStreaming 429 — backing off {$retryAfter}s (attempt {$attempt}/{$attempts})");
                usleep($retryAfter * 1_000_000);
                continue;
            }

            if ($response->status() < 500) {
                throw new ClaudeAgentException("Claude API Fehler {$response->status()}");
            }

            if ($attempt < $attempts) {
                usleep($sleepMs * (2 ** ($attempt - 1)) * 1000);
            }
        }

        if ($response === null || $response->failed()) {
            throw new ClaudeAgentException('Claude API Fehler nach '.$attempts.' Versuchen: '.($response?->status() ?? 'no response'));
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $inputTokens = 0;
        $outputTokens = 0;

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $raw = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                if (! preg_match('/^data:\s*(.+)$/m', $raw, $m)) {
                    continue;
                }
                $data = json_decode($m[1], true);
                if (! $data) {
                    continue;
                }

                $type = $data['type'] ?? '';

                if ($type === 'content_block_delta') {
                    $text = $data['delta']['text'] ?? '';
                    if ($text !== '') {
                        yield ['type' => 'content', 'text' => $text];
                    }
                } elseif ($type === 'message_start') {
                    $inputTokens = $data['message']['usage']['input_tokens'] ?? 0;
                } elseif ($type === 'message_delta') {
                    $outputTokens = $data['usage']['output_tokens'] ?? $outputTokens;
                }
            }
        }

        if ($workspace !== null && ($inputTokens + $outputTokens) > 0) {
            $this->creditService->deduct($workspace, $inputTokens, $configKey, $outputTokens);
        }

        yield ['type' => 'done', 'input_tokens' => $inputTokens, 'output_tokens' => $outputTokens];
    }

    /**
     * Mayring-Agent via Pi/Ollama-Server — keine Anthropic-Kosten.
     * Holt RAG-Kontext vorab via MayringMcpClient und übergibt alles an den Pi-Worker.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, raw: array, tokens_used: int}
     */
    private function callMayringViaPi(
        string $systemPrompt,
        array $messages,
        string $configKey,
        ?Workspace $workspace,
    ): array {
        $piUrl = rtrim((string) config('services.pi_agent.url', 'http://host.docker.internal:8091'), '/');

        // Letzten User-Message als Query für RAG-Vorsuche extrahieren
        $userMessage = collect($messages)->where('role', 'user')->last()['content'] ?? '';

        // RAG-Vorsuche: relevante Chunks vorab laden (kein Tool-Use-Loop nötig)
        $ragContext = '';
        if ($userMessage !== '') {
            try {
                $searchResult = $this->mayringClient->searchDocuments($userMessage, [], 5);
                $promptContext = $searchResult['prompt_context'] ?? '';
                if ($promptContext !== '') {
                    $ragContext = "\n\n## Relevante Dokument-Chunks\n".$promptContext;
                }
            } catch (\Throwable $e) {
                Log::warning('MayringViaPi: RAG-Vorsuche fehlgeschlagen', ['error' => $e->getMessage()]);
            }
        }

        $task = $systemPrompt.$ragContext."\n\n---\n\n".$userMessage;

        $response = Http::timeout(300)->post("{$piUrl}/pi-task", [
            'task' => $task,
        ]);

        if (! $response->successful()) {
            Log::error('ClaudeService: Pi-Agent Fehler', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new ClaudeAgentException(
                'Pi-Agent Fehler ('.$response->status().'): '.mb_substr($response->body(), 0, 300)
            );
        }

        $data = $response->json();
        $content = $data['content'] ?? '';

        Log::info('Claude mayring agent via Pi succeeded', [
            'config_key' => $configKey,
            'rag_chunks_loaded' => $ragContext !== '',
        ]);

        return [
            'content' => $content,
            'raw' => $data,
            'tokens_used' => 0,  // Kein API-Cost bei Pi-Routing
        ];
    }

    private function useOllamaForWorkers(): bool
    {
        return (bool) config('services.anthropic.use_ollama_workers', false);
    }

    private function resolveWorkspace(array $context): ?Workspace
    {
        $workspaceId = $context['workspace_id'] ?? null;

        if (! $workspaceId) {
            return null;
        }

        return Workspace::find($workspaceId);
    }

    /**
     * Für chat-agent: User-Preference (preferred_chat_model) schlägt Config-Default.
     * Für Worker-Agenten (scoping/search/review/...): immer Config (kein User-Override).
     */
    private function resolveModelForCall(string $configKey, array $context): string
    {
        $configured = config("services.anthropic.agent_models.{$configKey}")
            ?? config('services.anthropic.model', 'claude-haiku-4-5-20251001');

        if ($configKey !== 'chat-agent') {
            return $configured;
        }

        $user = $this->resolveUserForCall($context);
        if ($user === null) {
            return $configured;
        }

        return $user->resolvedChatModel();
    }

    private function resolveUserForCall(array $context): ?\App\Models\User
    {
        if (! empty($context['user_id'])) {
            return \App\Models\User::find($context['user_id']);
        }

        return \Illuminate\Support\Facades\Auth::user();
    }
}
