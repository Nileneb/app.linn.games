<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamingAgentService
{
    public function __construct(
        private readonly ClaudeService $claudeService,
        private readonly ContextProvider $contextProvider,
        private readonly AgentResultStorageService $storageService,
    ) {}

    public function stream(
        string $agentId,
        array $messages,
        int $timeout = 120,
        array $context = [],
    ): StreamedResponse {
        return new StreamedResponse(
            function () use ($agentId, $messages, $context): void {
                try {
                    $builtMessages = $this->buildMessages($messages, $context);
                    $fullText = '';
                    $index = 0;

                    foreach ($this->claudeService->callStreaming('chat-agent', $builtMessages, $context) as $event) {
                        if ($event['type'] === 'content') {
                            $fullText .= $event['text'];
                            $this->sendChunk([
                                'chunk' => $event['text'],
                                'index' => $index++,
                                'type' => 'content',
                            ]);
                            ob_flush();
                            flush();
                        }
                    }

                    $this->sendChunk([
                        'status' => 'done',
                        'total_chars' => mb_strlen($fullText),
                        'type' => 'complete',
                    ]);

                    $this->persistChat($fullText, $messages, $context);

                } catch (ClaudeAgentException $e) {
                    Log::error('StreamingAgentService: Claude-Agent fehlgeschlagen', [
                        'agent_id' => $agentId,
                        'error' => $e->getMessage(),
                    ]);

                    $this->sendChunk([
                        'status' => 'error',
                        'error' => $e->getMessage(),
                        'type' => 'error',
                    ]);
                } catch (\Throwable $e) {
                    Log::error('StreamingAgentService: Unerwarteter Fehler', [
                        'agent_id' => $agentId,
                        'error' => $e->getMessage(),
                    ]);

                    $this->sendChunk([
                        'status' => 'error',
                        'error' => 'Interner Fehler',
                        'type' => 'error',
                    ]);
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    private function buildMessages(array $messages, array $context): array
    {
        $projektId = $context['projekt_id'] ?? null;
        $workspaceId = $context['workspace_id'] ?? null;
        $userId = isset($context['user_id']) ? (string) $context['user_id'] : '';
        $userQuery = $this->extractLastUserQuery($messages);

        if ($projektId && $workspaceId && $userId !== '' && $userQuery !== '') {
            try {
                return $this->contextProvider->buildMessages(
                    $projektId, $workspaceId, $userId, $userQuery, $messages,
                );
            } catch (\Throwable $e) {
                Log::warning('StreamingAgentService: ContextProvider fehlgeschlagen', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $messages;
    }

    private function persistChat(string $assistantText, array $messages, array $context): void
    {
        $projektId = $context['projekt_id'] ?? null;
        $workspaceId = $context['workspace_id'] ?? null;
        $userId = isset($context['user_id']) ? (string) $context['user_id'] : '';

        if (! $projektId || ! $workspaceId || $userId === '' || $assistantText === '') {
            return;
        }

        $lines = [];
        foreach ($messages as $msg) {
            $role = ucfirst((string) ($msg['role'] ?? 'unknown'));
            $content = (string) ($msg['content'] ?? '');
            $lines[] = "## {$role}\n\n{$content}";
        }
        $lines[] = "## Assistant\n\n{$assistantText}";

        try {
            $this->storageService->storeChat(
                implode("\n\n---\n\n", $lines), $workspaceId, $userId, $projektId,
            );
        } catch (\Throwable $e) {
            Log::warning('StreamingAgentService: Chat-Persistierung fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractLastUserQuery(array $messages): string
    {
        foreach (array_reverse($messages) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                return (string) ($msg['content'] ?? '');
            }
        }

        return '';
    }

    private function sendChunk(array $data): void
    {
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
    }
}
