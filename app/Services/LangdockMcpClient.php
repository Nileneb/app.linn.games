<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\StreamInterface;

/**
 * MCP-Protokoll-Client für den offiziellen Langdock MCP-Server.
 *
 * Zuständig für Chat-Agent-Kommunikation ("Frontdesk") mit echtem Streaming
 * zurück an den Browser via SSE. Worker-Agents (P1–P8) verwenden weiterhin
 * LangdockAgentService (synchron, kein Streaming).
 *
 * Kommunikationsflow:
 *   Laravel (LangdockMcpClient)
 *     → HTTP POST an config('services.langdock.mcp_endpoint')
 *     → Server-Sent Events (SSE) als Antwort
 *     → Generator yieldet jeden Chunk an den Aufrufer
 *     → Aufrufer (z. B. StreamingMcpController) streamt Chunks via SSE an Browser
 */
class LangdockMcpClient
{
    private readonly Client $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client([
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Sendet eine Nachricht an den Langdock Chat-Agent via MCP-Protokoll
     * und gibt einen Generator zurück der die Streaming-Chunks liefert.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  string  $agentId  Langdock Agent-ID
     * @param  callable|null  $onChunk  Optionaler Callback, wird pro Chunk mit array{text: string, raw: array} aufgerufen
     * @return \Generator<int, array{text: string, raw: array}, mixed, void>
     *
     * @throws LangdockConnectionException
     */
    public function streamChatCompletion(
        array $messages,
        string $agentId,
        ?callable $onChunk = null,
    ): \Generator {
        $endpoint   = $this->resolveEndpoint();
        $apiKey     = config('services.langdock.api_key');
        $logContext = $this->buildLogContext($agentId, $messages);

        if (! $apiKey || ! $agentId) {
            Log::error('Langdock MCP configuration missing', $logContext + [
                'has_api_key' => (bool) $apiKey,
            ]);

            throw new LangdockConnectionException('Langdock API-Key oder Agent-ID nicht konfiguriert.');
        }

        $body         = $this->buildRequestBody($agentId, $messages);
        $startedAt    = microtime(true);
        $maxRetries   = (int) config('services.langdock.retry_attempts', 3);
        $retrySleepMs = (int) config('services.langdock.retry_sleep_ms', 500);
        $attempt      = 0;

        Log::info('Langdock MCP streaming request started', $logContext + [
            'endpoint' => $endpoint,
        ]);

        // Retry-Loop — Wiederholungen nur beim Verbindungsaufbau, nicht mid-stream
        while (true) {
            try {
                $response   = $this->httpClient->post($endpoint, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'text/event-stream',
                        'Cache-Control' => 'no-cache',
                    ],
                    'json'         => $body,
                    'stream'       => true,
                    'timeout'      => 0,   // Kein Gesamt-Timeout bei Streaming
                    'read_timeout' => 120,
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode >= 500 && $attempt < $maxRetries) {
                    $delayMs = $retrySleepMs * (2 ** $attempt);
                    Log::warning('Langdock MCP server error, retrying', $logContext + [
                        'attempt'  => $attempt + 1,
                        'max'      => $maxRetries,
                        'delay_ms' => $delayMs,
                        'status'   => $statusCode,
                    ]);
                    usleep($delayMs * 1000);
                    $attempt++;
                    continue;
                }

                if ($statusCode >= 400) {
                    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                    Log::error('Langdock MCP request failed', $logContext + [
                        'status'           => $statusCode,
                        'duration_ms'      => $durationMs,
                        'response_excerpt' => Str::limit((string) $response->getBody(), 1000),
                    ]);

                    throw new LangdockConnectionException(
                        "Langdock MCP API returned HTTP {$statusCode}",
                        $statusCode,
                    );
                }

                // Erfolgreiche Verbindung — Stream weiterverarbeiten
                yield from $this->parseStream($response->getBody(), $onChunk);

                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                Log::info('Langdock MCP streaming completed', $logContext + [
                    'duration_ms' => $durationMs,
                ]);

                return;

            } catch (LangdockConnectionException $e) {
                throw $e;

            } catch (ConnectException | RequestException $e) {
                if ($attempt < $maxRetries) {
                    $delayMs = $retrySleepMs * (2 ** $attempt);
                    Log::warning('Langdock MCP connection error, retrying', $logContext + [
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
                Log::error('Langdock MCP streaming crashed', $logContext + [
                    'duration_ms' => $durationMs,
                    'exception'   => $e::class,
                    'message'     => $e->getMessage(),
                ]);

                throw new LangdockConnectionException(
                    'Verbindung zum Langdock MCP-Server fehlgeschlagen: ' . $e->getMessage(),
                    0,
                    $e,
                );
            }
        }
    }

    /**
     * Sendet eine Nachricht (non-streaming) und gibt den vollständigen Text zurück.
     *
     * Intern iteriert diese Methode den Streaming-Generator und akkumuliert
     * alle Text-Chunks. Für einfache Anwendungsfälle wo kein SSE nötig ist.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  string  $agentId  Langdock Agent-ID
     *
     * @throws LangdockConnectionException
     */
    public function chatCompletion(array $messages, string $agentId): string
    {
        $fullText = '';

        foreach ($this->streamChatCompletion($messages, $agentId) as $chunk) {
            $fullText .= $chunk['text'] ?? '';
        }

        return $fullText;
    }

    /**
     * Liest den SSE-Stream aus und yieldet geparsete Chunks.
     *
     * SSE-Format:
     *   data: {"choices":[{"delta":{"content":"..."}}]}\n\n
     *   data: [DONE]\n\n
     *
     * @param  StreamInterface  $stream
     * @param  callable|null  $onChunk
     * @return \Generator<int, array{text: string, raw: array}, mixed, void>
     */
    private function parseStream(StreamInterface $stream, ?callable $onChunk): \Generator
    {
        $buffer = '';

        while (! $stream->eof()) {
            $read = $stream->read(4096);

            if ($read === '') {
                continue;
            }

            $buffer .= $read;

            // SSE-Events sind durch doppelten Zeilenumbruch getrennt
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event  = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $parsed = $this->parseSseEvent($event);

                if ($parsed === null) {
                    continue;
                }

                if ($onChunk !== null) {
                    ($onChunk)($parsed);
                }

                yield $parsed;
            }
        }

        // Verbleibenden Buffer verarbeiten (falls kein abschließender \n\n)
        if (trim($buffer) !== '') {
            $parsed = $this->parseSseEvent($buffer);

            if ($parsed !== null) {
                if ($onChunk !== null) {
                    ($onChunk)($parsed);
                }

                yield $parsed;
            }
        }
    }

    /**
     * Parst ein einzelnes SSE-Event-Block ("data: ..." Zeilen).
     *
     * Unterstützt folgende Response-Shapes:
     * - OpenAI-kompatibel: choices[0].delta.content
     * - Completion-Style:  choices[0].text
     * - Einfach:           content
     * - Message-wrapped:   message.content
     *
     * @param  string  $event  Rohtext eines SSE-Events (ohne abschließende Leerzeilen)
     * @return array{text: string, raw: array}|null  null bei [DONE] oder nicht-parsebarem Event
     */
    private function parseSseEvent(string $event): ?array
    {
        $dataLine = null;

        foreach (explode("\n", $event) as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'data: ')) {
                $dataLine = substr($line, 6);
            }
        }

        if ($dataLine === null || $dataLine === '[DONE]') {
            return null;
        }

        $decoded = json_decode($dataLine, true);

        if (! is_array($decoded)) {
            return null;
        }

        // Text-Extraktion aus verschiedenen Response-Shapes
        $text = $decoded['choices'][0]['delta']['content']   // OpenAI-kompatibel (Streaming)
            ?? $decoded['choices'][0]['text']                // Completion-Style
            ?? $decoded['content']                           // Einfach
            ?? $decoded['message']['content']                // Message-wrapped
            ?? '';

        return [
            'text' => (string) $text,
            'raw'  => $decoded,
        ];
    }

    /**
     * Baut den Request-Body für die MCP-API.
     *
     * Nutzt dasselbe Message-Format wie LangdockAgentService (parts-Array),
     * ergänzt um stream: true für SSE-Antwort.
     *
     * @param  string  $agentId
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    private function buildRequestBody(string $agentId, array $messages): array
    {
        return [
            'agentId'  => $agentId,
            'messages' => array_map(fn (array $msg) => [
                'id'    => (string) Str::uuid(),
                'role'  => $msg['role'],
                'parts' => [['type' => 'text', 'text' => $msg['content']]],
            ], $messages),
            'stream'   => true,
        ];
    }

    /**
     * Liest den MCP-Endpunkt aus der Konfiguration.
     *
     * Konfigurierbar via:
     *   LANGDOCK_MCP_ENDPOINT=https://api.langdock.com/mcp/v1
     */
    private function resolveEndpoint(): string
    {
        return (string) config('services.langdock.mcp_endpoint', 'https://api.langdock.com/mcp/v1');
    }

    /**
     * Baut den Log-Kontext für alle Log-Aufrufe.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    private function buildLogContext(string $agentId, array $messages): array
    {
        $lastMessage = $messages === [] ? null : $messages[array_key_last($messages)];

        return array_filter([
            'request_id'           => (string) Str::uuid(),
            'agent_id'             => $agentId,
            'message_count'        => count($messages),
            'last_message_role'    => $lastMessage['role'] ?? null,
            'last_message_preview' => isset($lastMessage['content'])
                ? Str::limit((string) preg_replace('/\s+/', ' ', $lastMessage['content']), 180)
                : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
