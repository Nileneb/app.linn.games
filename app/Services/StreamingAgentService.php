<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams agent responses character-by-character via Server-Sent Events (SSE).
 *
 * Usage:
 *   $service = app(StreamingAgentService::class);
 *   return $service->stream($agentId, $messages, $context);
 *
 * Response format (SSE):
 *   data: {"chunk":"Hello","index":0}\n\n
 *   data: {"chunk":" ","index":1}\n\n
 *   ...
 *   data: {"status":"done","total_chars":42}\n\n
 */
class StreamingAgentService
{
    private const CHUNK_SIZE = 1; // Stream char-by-char
    private const FLUSH_INTERVAL = 5; // Flush every N chunks

    public function __construct(
        private readonly LangdockAgentService $agentService,
    ) {}

    /**
     * Stream agent response as Server-Sent Events
     */
    public function stream(
        string $agentId,
        array $messages,
        int $timeout = 120,
        array $context = [],
    ): StreamedResponse {
        return new StreamedResponse(
            function () use ($agentId, $messages, $timeout, $context) {
                try {
                    // Call agent synchronously (streaming comes from our chunking)
                    $response = $this->agentService->call(
                        $agentId,
                        $messages,
                        $timeout,
                        $context,
                    );

                    $content = $response['content'] ?? '';
                    $totalChars = mb_strlen($content);
                    $index = 0;
                    $chunkCount = 0;

                    // Stream content character by character
                    for ($i = 0; $i < $totalChars; $i++) {
                        $char = mb_substr($content, $i, 1);
                        $this->sendChunk([
                            'chunk' => $char,
                            'index' => $index++,
                            'type' => 'content',
                        ]);

                        // Flush every N chunks for responsiveness
                        if (++$chunkCount % self::FLUSH_INTERVAL === 0) {
                            flush();
                        }
                    }

                    // Send completion signal
                    $this->sendChunk([
                        'status' => 'done',
                        'total_chars' => $totalChars,
                        'raw_response' => $response['raw'] ?? null,
                        'type' => 'complete',
                    ]);

                } catch (LangdockAgentException $e) {
                    Log::error('Streaming agent failed', [
                        'agent_id' => $agentId,
                        'error' => $e->getMessage(),
                    ]);

                    $this->sendChunk([
                        'status' => 'error',
                        'error' => $e->getMessage(),
                        'type' => 'error',
                    ]);
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no', // Disable nginx buffering
            ],
        );
    }

    /**
     * Send SSE chunk
     */
    private function sendChunk(array $data): void
    {
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    }
}
