<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ClaudeCliService
{
    /**
     * Production-Hardening-Flags für den Chat-Agent (Main Agent).
     * Whitelist-Ansatz: nur MCP Memory Tools + definierte Subagents erlaubt.
     *
     * @return string[]
     */
    private function productionChatFlags(): array
    {
        $mcpConfig = base_path('.claude/mcp-production.json');

        return array_filter([
            file_exists($mcpConfig) ? '--mcp-config' : null,
            file_exists($mcpConfig) ? escapeshellarg($mcpConfig) : null,
            '--allowedTools', implode(',', [
                'mcp__memory__search_memory',
                'mcp__memory__put',
                'mcp__memory__get',
                'mcp__memory__list_by_source',
                'mcp__memory__invalidate',
            ]),
            '--agents', $this->buildAllowedAgentsJson(),
        ]);
    }

    /**
     * Production-Hardening-Flags für Phase-Agents (Worker).
     * Worker sind reine Text-in/Text-out Agents — keine Tools.
     *
     * @return string[]
     */
    private function productionWorkerFlags(?string $mcpConfigOverride = null): array
    {
        $paperSearchTools = [
            'mcp__paper-search__search_papers',
            'mcp__paper-search__search_arxiv',
            'mcp__paper-search__search_pubmed',
            'mcp__paper-search__search_biorxiv',
            'mcp__paper-search__search_medrxiv',
            'mcp__paper-search__search_google_scholar',
            'mcp__paper-search__search_semantic',
            'mcp__paper-search__search_crossref',
            'mcp__paper-search__search_iacr',
            'mcp__paper-search__get_crossref_paper_by_doi',
            'mcp__paper-search__read_arxiv_paper',
            'mcp__paper-search__read_pubmed_paper',
            'mcp__paper-search__read_biorxiv_paper',
            'mcp__paper-search__read_medrxiv_paper',
            'mcp__paper-search__read_iacr_paper',
            'mcp__paper-search__read_semantic_paper',
            'mcp__paper-search__read_crossref_paper',
            'mcp__paper-search__ingest_paper',
            'mcp__paper-search__search_rag_papers',
        ];

        $mcpConfig = $mcpConfigOverride ?? base_path('.claude/mcp-production.json');

        // --mcp-config is variadic (<configs...>) — must come BEFORE --allowedTools
        // to prevent it from swallowing subsequent arguments as config paths.
        return array_filter([
            file_exists($mcpConfig) ? '--mcp-config' : null,
            file_exists($mcpConfig) ? escapeshellarg($mcpConfig) : null,
            '--allowedTools', implode(',', $paperSearchTools),
        ]);
    }

    /**
     * Baut das JSON für --agents Flag.
     * Definiert die erlaubten Subagents (Worker 1-3) — nur diese darf der Main Agent erstellen.
     */
    private function buildAllowedAgentsJson(): string
    {
        $agents = [];

        $agentFiles = [
            'worker-1-cluster',
            'worker-2-search',
            'worker-3-quality',
        ];

        foreach ($agentFiles as $agentFile) {
            $path = base_path(".claude/agents/{$agentFile}.md");
            if (file_exists($path)) {
                $content = file_get_contents($path);
                // YAML-Frontmatter parsen
                if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)/s', $content, $m)) {
                    $frontmatter = [];
                    foreach (explode("\n", $m[1]) as $line) {
                        if (preg_match('/^(\w+):\s*(.+)$/', trim($line), $kv)) {
                            $frontmatter[$kv[1]] = $kv[2];
                        }
                    }
                    $agents[$agentFile] = [
                        'description' => $frontmatter['description'] ?? $agentFile,
                        'prompt' => trim($m[2]),
                    ];
                    if (isset($frontmatter['model'])) {
                        $agents[$agentFile]['model'] = $frontmatter['model'];
                    }
                }
            }
        }

        return escapeshellarg(json_encode($agents, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Ruft den Main Agent via Claude CLI subprocess auf.
     *
     * @param  array<string, mixed>  $context  projekt_id, workspace_id, phase_nr, user_id, ...
     * @return array{content: string}
     *
     * @throws ClaudeCliException
     */
    private function cliBin(): string
    {
        return config('services.anthropic.cli_path', base_path('node_modules/.bin/claude'));
    }

    private function useDirectApi(): bool
    {
        return (bool) config('services.anthropic.use_direct_api', false);
    }

    private function useOllamaForWorkers(): bool
    {
        return (bool) config('services.anthropic.use_ollama_workers', false);
    }

    /**
     * Ruft einen Phase-Worker via Pi-Server (MayringCoder + Ollama + RAG) auf.
     * Dev-Mode: kein API-Cost, Memory-gestütztes lokales Modell.
     *
     * @return array{content: string, cost_usd: float, input_tokens: int, output_tokens: int}
     *
     * @throws ClaudeCliException
     */
    private function callPiAgent(
        string $systemPrompt,
        string $userMessage,
        int $timeout = 300,
    ): array {
        $piUrl = rtrim(config('services.pi_agent.url', 'http://host.docker.internal:8091'), '/');

        // Kombiniere System-Prompt + User-Message als Pi-Task
        $task = $systemPrompt !== ''
            ? "{$systemPrompt}\n\n---\n\n{$userMessage}"
            : $userMessage;

        $response = Http::timeout($timeout)->post("{$piUrl}/pi-task", [
            'task' => $task,
        ]);

        if (! $response->successful()) {
            Log::error('Pi-Agent Fehler', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
                'pi_url' => $piUrl,
            ]);

            throw new ClaudeCliException(
                'Pi-Agent Fehler ('.$response->status().'): '.mb_substr($response->body(), 0, 300)
            );
        }

        $data = $response->json();
        $content = $data['content'] ?? '';

        return [
            'content' => $content,
            'cost_usd' => 0.0,
            'input_tokens' => 0,
            'output_tokens' => 0,
        ];
    }

    /**
     * Direkte Anthropic API — wird genutzt wenn CLAUDE_USE_DIRECT_API=true (Dev-Mode).
     *
     * @return array{content: string, cost_usd: float, input_tokens: int, output_tokens: int}
     *
     * @throws ClaudeCliException
     */
    private function callDirectApi(
        string $model,
        string $systemPrompt,
        string $userMessage,
        ?string $apiKey = null,
        int $timeout = 120,
    ): array {
        $apiKey = $apiKey ?: config('services.anthropic.api_key');

        $payload = [
            'model' => $model,
            'max_tokens' => (int) config('services.anthropic.max_tokens', 8192),
            'messages' => [['role' => 'user', 'content' => $userMessage]],
        ];

        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout($timeout)->post('https://api.anthropic.com/v1/messages', $payload);

        if (! $response->successful()) {
            Log::error('Anthropic API Fehler', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
                'model' => $model,
            ]);

            throw new ClaudeCliException(
                'Anthropic API Fehler ('.$response->status().'): '.mb_substr($response->body(), 0, 300)
            );
        }

        $data = $response->json();
        $content = $data['content'][0]['text'] ?? '';
        $usage = $data['usage'] ?? [];

        return [
            'content' => $content,
            'cost_usd' => 0.0,
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
        ];
    }

    public function call(string $userMessage, array $context = []): array
    {
        try {
            $systemSuffix = ! empty($context['projekt_id'])
                ? app(ClaudeContextBuilder::class)->build($context)
                : $this->buildContextBlock($context);
        } catch (\Throwable) {
            $systemSuffix = $this->buildContextBlock($context);
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        $providerConfig = $user instanceof \App\Models\User
            ? $user->llmProviderConfig()
            : ['type' => 'platform'];

        // OpenAI-compatible (Ollama, OpenRouter, LM Studio): dedicated adapter
        if ($providerConfig['type'] === 'openai-compatible') {
            return app(OpenAiCompatibleService::class)->chat(
                endpoint: $providerConfig['endpoint'],
                apiKey: $providerConfig['api_key'] ?? null,
                model: $providerConfig['model'],
                systemPrompt: $systemSuffix,
                userMessage: $userMessage,
            );
        }

        // Anthropic BYO or Platform: both go through Anthropic API/CLI.
        // Difference: API key source.
        $chatModel = $providerConfig['model']
            ?? ($user instanceof \App\Models\User
                ? $user->resolvedChatModel()
                : config('services.anthropic.agent_models.chat-agent',
                    config('services.anthropic.model', 'claude-sonnet-4-6')));

        $apiKey = $providerConfig['type'] === 'anthropic-byo'
            ? $providerConfig['api_key']
            : config('services.anthropic.api_key');

        if ($this->useDirectApi()) {
            $result = $this->callDirectApi($chatModel, $systemSuffix, $userMessage, $apiKey);

            return ['content' => $result['content']];
        }

        $parts = array_filter([
            escapeshellarg($this->cliBin()),
            '--print',
            '--output-format', 'json',
            '--model', escapeshellarg($chatModel),
            ...$this->productionChatFlags(),
            $systemSuffix !== '' ? '--append-system-prompt' : null,
            $systemSuffix !== '' ? escapeshellarg($systemSuffix) : null,
            '--',
            escapeshellarg($userMessage),
        ]);

        $command = implode(' ', array_values($parts));

        $env = [];
        if ($apiKey) {
            $env['ANTHROPIC_API_KEY'] = $apiKey;
        }

        $result = Process::timeout(120)->env($env)->run($command);

        if (! $result->successful()) {
            Log::error('Claude CLI subprocess fehlgeschlagen', [
                'exit_code' => $result->exitCode(),
                'stderr' => $result->errorOutput(),
                'stdout' => mb_substr($result->output(), 0, 500),
                'context' => $context,
            ]);

            throw new ClaudeCliException(
                'Claude CLI fehlgeschlagen (Exit '.$result->exitCode().'): '.$result->errorOutput()
            );
        }

        $decoded = json_decode($result->output(), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || ($decoded['is_error'] ?? false)) {
            throw new ClaudeCliException('Claude CLI: ungültiger JSON-Output: '.$result->output());
        }

        return [
            'content' => $decoded['result'] ?? '',
        ];
    }

    /**
     * Ruft einen Phase-Agent via Claude CLI auf.
     *
     * Nutzt --model für Worker-Modell-Auswahl und --append-system-prompt
     * für System-Prompt (PromptLoaderService + ClaudeContextBuilder).
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, cost_usd: float, input_tokens: int, output_tokens: int}
     *
     * @throws ClaudeCliException
     */
    public function callForPhase(
        string $agentConfigKey,
        array $messages,
        array $context = [],
        int $timeout = 300,
        ?string $mcpConfigPath = null,
    ): array {
        $model = config("services.anthropic.agent_models.{$agentConfigKey}")
            ?? config('services.anthropic.model', 'claude-haiku-4-5-20251001');

        $systemPrompt = $this->buildPhaseSystemPrompt($agentConfigKey, $context);

        $userMessage = collect($messages)
            ->where('role', 'user')
            ->last()['content'] ?? '';

        if ($this->useOllamaForWorkers()) {
            return $this->callPiAgent($systemPrompt, $userMessage, $timeout);
        }

        if ($this->useDirectApi()) {
            return $this->callDirectApi($model, $systemPrompt, $userMessage, $timeout);
        }

        $parts = array_filter([
            escapeshellarg($this->cliBin()),
            '--print',
            '--output-format', 'json',
            '--model', escapeshellarg($model),
            ...$this->productionWorkerFlags($mcpConfigPath),
            $systemPrompt !== '' ? '--append-system-prompt' : null,
            $systemPrompt !== '' ? escapeshellarg($systemPrompt) : null,
            '--',
            escapeshellarg($userMessage),
        ]);

        $command = implode(' ', array_values($parts));

        $env = [];
        $apiKey = config('services.anthropic.api_key');
        if ($apiKey) {
            $env['ANTHROPIC_API_KEY'] = $apiKey;
        }

        $result = Process::timeout($timeout)->env($env)->run($command);

        if (! $result->successful()) {
            Log::error('Claude CLI phase agent fehlgeschlagen', [
                'exit_code' => $result->exitCode(),
                'stderr' => $result->errorOutput(),
                'stdout' => mb_substr($result->output(), 0, 500),
                'agent_config_key' => $agentConfigKey,
                'context' => $context,
            ]);

            throw new ClaudeCliException(
                'Claude CLI fehlgeschlagen (Exit '.$result->exitCode().'): '.$result->errorOutput()
            );
        }

        $decoded = json_decode($result->output(), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || ($decoded['is_error'] ?? false)) {
            throw new ClaudeCliException('Claude CLI: ungültiger JSON-Output: '.mb_substr($result->output(), 0, 500));
        }

        return [
            'content' => $decoded['result'] ?? '',
            'cost_usd' => (float) ($decoded['total_cost_usd'] ?? 0),
            'input_tokens' => (int) ($decoded['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int) ($decoded['usage']['output_tokens'] ?? 0),
        ];
    }

    /**
     * Baut den System-Prompt für einen Phase-Agent.
     * Kombiniert PromptLoaderService (Agent-Prompt + Skills) mit ClaudeContextBuilder (DB-Kontext).
     */
    private function buildPhaseSystemPrompt(string $agentConfigKey, array $context): string
    {
        $promptFile = config("services.anthropic.agents.{$agentConfigKey}", '');

        if ($promptFile === '') {
            return $this->buildContextBlock($context);
        }

        $parts = [];

        try {
            $systemPrompt = app(PromptLoaderService::class)->buildSystemPrompt($promptFile);
            $parts[] = $systemPrompt;
        } catch (\Throwable $e) {
            Log::warning('ClaudeCliService: PromptLoaderService fehlgeschlagen', [
                'agent_config_key' => $agentConfigKey,
                'prompt_file' => $promptFile,
                'error' => $e->getMessage(),
            ]);
        }

        if (! empty($context)) {
            $contextBlock = app(ClaudeContextBuilder::class)->build($context);
            if ($contextBlock !== '') {
                $parts[] = $contextBlock;
            }
        }

        return implode("\n\n---\n\n", $parts);
    }

    private function buildContextBlock(array $context): string
    {
        if (empty($context)) {
            return '';
        }

        $lines = ['## Kontext'];
        foreach ($context as $key => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = "- **{$key}:** {$value}";
            }
        }

        return implode("\n", $lines);
    }
}
