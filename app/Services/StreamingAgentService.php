<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams chat-agent responses as Server-Sent Events (SSE).
 *
 * Verwendet ClaudeService für synchrone API-Aufrufe, sendet den Response
 * in 100-Zeichen-Chunks als SSE. ContextProvider baut die Messages inkl.
 * System-Message und RAG-Kontext. Nach vollständigem Stream wird das
 * Gespräch via AgentResultStorageService persistiert.
 *
 * SSE-Format (Browser-Seite):
 *   data: {"chunk":"Hallo","index":0,"type":"content"}\n\n
 *   ...
 *   data: {"status":"done","total_chars":42,"type":"complete"}\n\n
 */
class StreamingAgentService
{
    public function __construct(
        private readonly ClaudeCliService $claudeCliService,
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

                    // Main Agent via Claude CLI subprocess aufrufen
                    $userMessage = collect($builtMessages)
                        ->where('role', 'user')
                        ->last()['content'] ?? '';

                    $result = $this->claudeCliService->call($userMessage, $context);

                    $fullText = $result['content'];
                    $index = 0;

                    // Text chunk-weise senden (simuliertes Streaming, 100 Zeichen pro Chunk)
                    foreach (str_split($fullText, 100) as $chunk) {
                        $this->sendChunk([
                            'chunk' => $chunk,
                            'index' => $index++,
                            'type' => 'content',
                        ]);
                        ob_flush();
                        flush();
                    }

                    // Abschluss-Signal senden
                    $this->sendChunk([
                        'status' => 'done',
                        'total_chars' => mb_strlen($fullText),
                        'type' => 'complete',
                    ]);

                    // Chat persistieren (nur wenn Projekt-Kontext vorhanden)
                    $this->persistChat($fullText, $messages, $context);

                } catch (ClaudeAgentException|ClaudeCliException $e) {
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

    /**
     * Baut das endgültige Messages-Array für den Agent-Call.
     *
     * Mit Projekt-Kontext (projekt_id + workspace_id + user_id) wird
     * ContextProvider::buildMessages() aufgerufen — dieser fügt eine
     * System-Message mit Projekt-Metadaten und RAG-Chunks voran und
     * gibt [system_message, ...chatHistory] zurück.
     *
     * Ohne Kontext oder bei Fehler im ContextProvider werden die
     * Roh-Messages unverändert an den MCP-Client weitergegeben.
     */
    private function buildMessages(array $messages, array $context): array
    {
        $projektId = $context['projekt_id'] ?? null;
        $workspaceId = $context['workspace_id'] ?? null;
        $userId = isset($context['user_id']) ? (string) $context['user_id'] : '';
        $userQuery = $this->extractLastUserQuery($messages);

        if ($projektId && $workspaceId && $userId !== '' && $userQuery !== '') {
            try {
                return $this->contextProvider->buildMessages(
                    $projektId,
                    $workspaceId,
                    $userId,
                    $userQuery,
                    $messages,
                );
            } catch (\Throwable $e) {
                Log::warning('StreamingAgentService: ContextProvider fehlgeschlagen, Fallback auf Roh-Messages', [
                    'projekt_id' => $projektId,
                    'workspace_id' => $workspaceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $messages;
    }

    /**
     * Speichert das vollständige Gespräch als Markdown-Protokoll.
     *
     * Ruft AgentResultStorageService::storeChat() auf, welcher die Datei
     * unter agent-results/{workspace_id}/{user_id}/{projekt_id}/chat__{ts}.md ablegt
     * und IngestAgentResultJob für die RAG-Einbettung dispatcht.
     *
     * Wird still übergangen wenn Projekt-Kontext unvollständig oder Text leer ist.
     */
    private function persistChat(string $assistantText, array $messages, array $context): void
    {
        $projektId = $context['projekt_id'] ?? null;
        $workspaceId = $context['workspace_id'] ?? null;
        $userId = isset($context['user_id']) ? (string) $context['user_id'] : '';

        if (! $projektId || ! $workspaceId || $userId === '' || $assistantText === '') {
            return;
        }

        // Gespräch als Markdown-Protokoll aufbauen
        $lines = [];
        foreach ($messages as $msg) {
            $role = ucfirst((string) ($msg['role'] ?? 'unknown'));
            $content = (string) ($msg['content'] ?? '');
            $lines[] = "## {$role}\n\n{$content}";
        }
        $lines[] = "## Assistant\n\n{$assistantText}";

        $chatMarkdown = implode("\n\n---\n\n", $lines);

        try {
            $this->storageService->storeChat($chatMarkdown, $workspaceId, $userId, $projektId);
        } catch (\Throwable $e) {
            Log::warning('StreamingAgentService: Chat-Persistierung fehlgeschlagen', [
                'projekt_id' => $projektId,
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extrahiert den Inhalt der letzten User-Nachricht aus dem Messages-Array.
     * Wird von buildMessages() für den RAG-Query im ContextProvider benötigt.
     */
    private function extractLastUserQuery(array $messages): string
    {
        foreach (array_reverse($messages) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                return (string) ($msg['content'] ?? '');
            }
        }

        return '';
    }

    /**
     * Sendet einen SSE-Chunk an den Browser.
     */
    private function sendChunk(array $data): void
    {
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
    }
}
