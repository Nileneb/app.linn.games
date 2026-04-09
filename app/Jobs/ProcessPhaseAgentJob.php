<?php

namespace App\Jobs;

use App\Actions\SendAgentMessage;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Services\AgentPayloadService;
use App\Services\LangdockArtifactService;
use App\Services\PhaseChainService;
use App\Services\RetrieverService;
use App\Services\SynthesisMarkdownService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPhaseAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180; // Queue worker timeout (not HTTP)

    /** @var array<object>|null Retrieved chunks for synthesis */
    private ?array $retrievedChunks = null;

    public function __construct(
        public readonly string $projektId,
        public readonly int $phaseNr,
        public readonly string $agentConfigKey,
        public readonly array $messages,
        public readonly array $context,
    ) {}

    public function handle(): void
    {
        $result = null;

        try {
            $projekt = Projekt::findOrFail($this->projektId);

            $messages = $this->prependRetrieverContext($projekt, $this->messages);

            // Find the pending record created by the Livewire component before dispatch;
            // fall back to creating one here for backward compatibility (e.g. direct job dispatch).
            $result = PhaseAgentResult::where('projekt_id', $this->projektId)
                ->where('phase_nr', $this->phaseNr)
                ->where('agent_config_key', $this->agentConfigKey)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->first()
                ?? PhaseAgentResult::create([
                    'projekt_id'       => $this->projektId,
                    'user_id'          => $this->context['user_id'] ?? $projekt->user_id,
                    'phase_nr'         => $this->phaseNr,
                    'agent_config_key' => $this->agentConfigKey,
                    'status'           => 'pending',
                ]);

            $response = app(SendAgentMessage::class)->execute(
                $this->agentConfigKey,
                $messages,
                120, // HTTP timeout for the LLM call
                $this->context
            );

            if ($response['success']) {
                // Parse agent response for db_payload processing
                $parsed = $this->parseStructuredResponse((string) $response['content']);

                // Persist db_payload to database if present
                if ($parsed !== null) {
                    $payloadResult = app(AgentPayloadService::class)->persistPayload(
                        $parsed,
                        $this->projektId
                    );

                    Log::info('Phase agent job: db_payload processed', [
                        'projekt_id' => $this->projektId,
                        'phase_nr' => $this->phaseNr,
                        'tables_written' => $payloadResult['tables_written'],
                        'rows_written' => $payloadResult['rows_written'],
                    ]);
                }

                // Enhance agent response with synthesis markdown if chunks were retrieved
                $enhancedContent = $this->enrichResponseWithSynthesis(
                    (string) $response['content'],
                    $this->retrievedChunks ?? []
                );

                $artifact = app(LangdockArtifactService::class)->persistFromAgentResponse(
                    $enhancedContent,
                    $this->context,
                    [
                        'scope' => 'phase',
                        'phase_nr' => $this->phaseNr,
                        'config_key' => $this->agentConfigKey,
                        'basename' => "p{$this->phaseNr}-{$this->agentConfigKey}",
                        // "Am Ende" immer als Markdown-Artefakt persistieren.
                        'always_write_md' => $this->phaseNr === 8,
                    ],
                );

                $result->markCompleted($artifact['display_content']);
                Log::info('Phase agent job completed', [
                    'projekt_id' => $this->projektId,
                    'phase_nr' => $this->phaseNr,
                    'agent_config_key' => $this->agentConfigKey,
                    'chunks_used' => count($this->retrievedChunks ?? []),
                    'artifacts_stored' => count($artifact['stored_paths'] ?? []),
                ]);

                // Aktiven Nutzer aus dem Kontext propagieren, damit die Phasenkette
                // den richtigen user_id erhält statt immer den Projekt-Ersteller (Issue #154)
                app(PhaseChainService::class)->maybeDispatchNext(
                    $projekt,
                    $this->phaseNr,
                    $this->context['user_id'] ?? null,
                );
            } else {
                $result->markFailed($response['content']);
                Log::warning('Phase agent job failed', [
                    'projekt_id' => $this->projektId,
                    'phase_nr' => $this->phaseNr,
                    'agent_config_key' => $this->agentConfigKey,
                    'error' => $response['content'],
                ]);
            }
        } catch (\Throwable $e) {
            if ($result !== null) {
                $result->markFailed(__('Verarbeitung fehlgeschlagen: ').$e->getMessage());
            }
            Log::error('Phase agent job exception', [
                'projekt_id' => $this->projektId,
                'phase_nr' => $this->phaseNr,
                'agent_config_key' => $this->agentConfigKey,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Optionally prepend relevant document chunks to the messages array.
     * Skips silently when no paper_embeddings exist or Ollama is unavailable.
     *
     * Stores retrieved chunks for later synthesis generation.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    private function prependRetrieverContext(Projekt $projekt, array $messages): array
    {
        $query = $this->context['retriever_query'] ?? $projekt->forschungsfrage ?? null;

        if ($query === null || $query === '') {
            return $messages;
        }

        $retriever = app(RetrieverService::class);
        $chunks = $retriever->retrieve($query, $this->projektId);

        if (empty($chunks)) {
            return $messages;
        }

        // Store chunks for synthesis generation
        $this->retrievedChunks = $chunks;

        $contextText = $retriever->formatAsContext($chunks);

        Log::info('Retriever: '.count($chunks).' Chunks für Phase-Agent vorbereitet', [
            'projekt_id' => $this->projektId,
            'phase_nr' => $this->phaseNr,
            'chunks' => count($chunks),
        ]);

        return [['role' => 'user', 'content' => $contextText], ...$messages];
    }

    /**
     * Enrich agent response with synthesis markdown that includes source traceability.
     *
     * Parses the JSON response, generates synthesis markdown with HTML comments
     * encoding paper_id, chunk_index, and similarity scores, then embeds the
     * markdown in the response as md_files for persistence.
     *
     * @param  string  $rawContent  Raw agent response (JSON or text)
     * @param  array<object>  $retrievedChunks  Document chunks used in context
     * @return string Enhanced response with synthesis markdown embedded
     */
    private function enrichResponseWithSynthesis(string $rawContent, array $retrievedChunks): string
    {
        // Try to parse structured JSON response
        $parsed = $this->parseStructuredResponse($rawContent);

        if ($parsed === null || empty($retrievedChunks)) {
            return $rawContent; // Return unchanged if no structure or no chunks
        }

        try {
            // Generate synthesis markdown with full traceability
            $synthesisService = app(SynthesisMarkdownService::class);

            // Extract agent data from either meta/result or flat structure
            $agentData = $parsed['result']['data'] ?? $parsed['data'] ?? [];

            $synthesisMarkdown = $synthesisService->generateSynthesis(
                $this->phaseNr,
                $agentData,
                $retrievedChunks
            );

            // Ensure md_files array exists in response (support both structures)
            if (isset($parsed['result']['data'])) {
                if (! isset($parsed['result']['data']['md_files'])) {
                    $parsed['result']['data']['md_files'] = [];
                }
                if (! is_array($parsed['result']['data']['md_files'])) {
                    $parsed['result']['data']['md_files'] = [];
                }
                $parsed['result']['data']['md_files'][] = [
                    'path' => "synthesis_p{$this->phaseNr}.md",
                    'content' => $synthesisMarkdown,
                ];
            } elseif (isset($parsed['data'])) {
                if (! isset($parsed['data']['md_files'])) {
                    $parsed['data']['md_files'] = [];
                }
                if (! is_array($parsed['data']['md_files'])) {
                    $parsed['data']['md_files'] = [];
                }
                $parsed['data']['md_files'][] = [
                    'path' => "synthesis_p{$this->phaseNr}.md",
                    'content' => $synthesisMarkdown,
                ];
            }

            // Return enhanced JSON
            return json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::warning('Failed to generate synthesis markdown', [
                'projekt_id' => $this->projektId,
                'phase_nr' => $this->phaseNr,
                'exception' => $e->getMessage(),
            ]);

            return $rawContent; // Fall back to original response
        }
    }

    /**
     * Try to parse structured JSON response envelope.
     *
     * @return array{meta?: array, result?: array, data?: array}|null
     */
    private function parseStructuredResponse(string $rawContent): ?array
    {
        $trimmed = trim($rawContent);

        if ($trimmed === '' || ! str_starts_with($trimmed, '{')) {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                return null;
            }

            // Look for either meta/result structure or flat data structure
            if (isset($decoded['meta'], $decoded['result'])) {
                return $decoded;
            }

            // Also accept flat structure with data key
            if (isset($decoded['data'])) {
                return ['data' => $decoded['data']];
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }
}
