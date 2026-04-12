<?php

namespace App\Jobs;

use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\Workspace;
use App\Services\AgentDailyLimitExceededException;
use App\Services\AgentPayloadService;
use App\Services\AgentResponseParser;
use App\Services\ClaudeCliService;
use App\Services\CreditService;
use App\Services\LangdockArtifactService;
use App\Services\PaperSearchService;
use App\Services\PhaseChainService;
use App\Services\RetrieverService;
use App\Services\SynthesisMarkdownService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessPhaseAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 360; // Must exceed ClaudeCliService::callForPhase() timeout (300s)

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
            $messages = $this->prependPaperSearchContext($projekt, $messages);

            // Find the pending record created by the Livewire component before dispatch;
            // fall back to creating one here for backward compatibility (e.g. direct job dispatch).
            $result = PhaseAgentResult::where('projekt_id', $this->projektId)
                ->where('phase_nr', $this->phaseNr)
                ->where('agent_config_key', $this->agentConfigKey)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->first()
                ?? PhaseAgentResult::create([
                    'projekt_id' => $this->projektId,
                    'user_id' => $this->context['user_id'] ?? $projekt->user_id,
                    'phase_nr' => $this->phaseNr,
                    'agent_config_key' => $this->agentConfigKey,
                    'status' => 'pending',
                ]);

            // Tageslimit vorab prüfen — bevor der API-Call Kosten verursacht.
            // Bei Limit: Status auf 'deferred', Job für morgen 00:05 Uhr einplanen.
            $workspaceIdForCheck = $this->context['workspace_id'] ?? $projekt->workspace_id;
            if ($workspaceIdForCheck) {
                $workspaceForCheck = Workspace::find($workspaceIdForCheck);
                if ($workspaceForCheck) {
                    try {
                        app(CreditService::class)->assertDailyLimitNotReached($workspaceForCheck, $this->agentConfigKey);
                    } catch (AgentDailyLimitExceededException $e) {
                        $scheduledFor = Carbon::tomorrow()->setTime(0, 5, 0)->format('d.m.Y H:i');
                        $result->markDeferred($scheduledFor);

                        self::dispatch(
                            $this->projektId,
                            $this->phaseNr,
                            $this->agentConfigKey,
                            $this->messages,
                            $this->context,
                        )->delay(Carbon::tomorrow()->setTime(0, 5, 0));

                        Log::info('Phase agent job deferred — Tageslimit erreicht', [
                            'projekt_id' => $this->projektId,
                            'phase_nr' => $this->phaseNr,
                            'agent_config_key' => $this->agentConfigKey,
                            'scheduled_for' => $scheduledFor,
                        ]);

                        return;
                    }
                }
            }

            // Agent via Claude CLI subprocess aufrufen
            // structured_output: true instructs ClaudeContextBuilder to append the JSON Envelope
            // requirement to the system prompt so workers persist data via db_payload.
            $contextWithOutput = array_merge($this->context, [
                'structured_output' => true,
                'agent_config_key' => $this->agentConfigKey,
            ]);

            $cliResult = app(ClaudeCliService::class)->callForPhase(
                $this->agentConfigKey,
                $messages,
                $contextWithOutput,
            );

            $rawContent = $cliResult['content'];

            // Token-Tracking via CreditService
            // Bevorzuge echte cost_usd vom CLI (inkl. Cache-Writes) über Token-Schätzung
            $workspaceId = $this->context['workspace_id'] ?? $projekt->workspace_id;
            $hasTokenData = $cliResult['input_tokens'] > 0 || $cliResult['output_tokens'] > 0;
            $hasCostData = ($cliResult['cost_usd'] ?? 0.0) > 0.0;
            if ($workspaceId && ($hasTokenData || $hasCostData)) {
                $workspace = Workspace::find($workspaceId);
                if ($workspace) {
                    app(CreditService::class)->deduct(
                        $workspace,
                        $cliResult['input_tokens'],
                        $this->agentConfigKey,
                        $cliResult['output_tokens'],
                        $cliResult['cost_usd'] ?? 0.0,
                    );
                }
            }

            // Dedizierter Parser statt inline Logik
            $parsed = app(AgentResponseParser::class)->parse($rawContent);

            // Debug: Pi agent output sichtbar machen wenn kein db_payload
            if ($parsed['db_payload'] === null) {
                // Try raw JSON decode to surface the parse error
                json_decode(trim($rawContent), true);
                $jsonErr = json_last_error() !== JSON_ERROR_NONE
                    ? json_last_error_msg().' (pos ~'.strrpos(mb_substr($rawContent, 0, 2000), '{').')'
                    : 'no json_error (db_payload key missing)';

                Log::debug('ProcessPhaseAgentJob: kein db_payload im Agent-Response', [
                    'projekt_id' => $this->projektId,
                    'phase_nr' => $this->phaseNr,
                    'json_error' => $jsonErr,
                    'response_len' => strlen($rawContent),
                    'response_preview' => mb_substr($rawContent, 0, 800),
                ]);
            }

            // DB-Payload persistieren
            if ($parsed['db_payload'] !== null) {
                $payloadResult = app(AgentPayloadService::class)->persistPayload(
                    ['db_payload' => $parsed['db_payload']],
                    $this->projektId
                );

                Log::info('Phase agent job: db_payload processed', [
                    'projekt_id' => $this->projektId,
                    'phase_nr' => $this->phaseNr,
                    'tables_written' => $payloadResult['tables_written'],
                    'rows_written' => $payloadResult['rows_written'],
                ]);
            }

            // Qualitätsscore für P1 in result_data persistieren
            if ($this->phaseNr === 1) {
                $bewertung = $parsed['meta']['qualitaets_bewertung'] ?? null;
                if (is_array($bewertung) && isset($bewertung['score'])) {
                    $result->update(['result_data' => ['qualitaets_bewertung' => $bewertung]]);
                }
            }

            // Synthesis-Markdown anreichern
            $enhancedContent = $this->enrichResponseWithSynthesis(
                $rawContent,
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
                'cost_usd' => $cliResult['cost_usd'],
            ]);

            // Auto-Chain: nächste Phase dispatchen
            app(PhaseChainService::class)->maybeDispatchNext(
                $projekt,
                $this->phaseNr,
                $this->context['user_id'] ?? null,
            );
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
     * Called by the queue worker when the job is killed (timeout / SIGKILL / max attempts).
     * Without this, PhaseAgentResult stays pending forever.
     */
    public function failed(?\Throwable $exception = null): void
    {
        $result = PhaseAgentResult::where('projekt_id', $this->projektId)
            ->where('phase_nr', $this->phaseNr)
            ->where('agent_config_key', $this->agentConfigKey)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->first();

        if ($result) {
            $message = $exception?->getMessage() ?? 'Job abgebrochen (Timeout oder Worker-Fehler)';
            $result->markFailed($message);
        }

        Log::error('Phase agent job failed/killed', [
            'projekt_id' => $this->projektId,
            'phase_nr' => $this->phaseNr,
            'agent_config_key' => $this->agentConfigKey,
            'exception' => $exception?->getMessage(),
        ]);
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
     * For phases 3, 4, 5: fetch real papers from paper-search MCP REST API and
     * prepend them as context so the Pi agent has actual data to work with.
     *
     * Phase 3: database selection — needs papers to know which DBs are relevant
     * Phase 4: search string generation — needs papers to refine/validate strings
     * Phase 5: screening — needs papers to create p5_treffer records
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    private function prependPaperSearchContext(Projekt $projekt, array $messages): array
    {
        // Only run for phases that benefit from live paper data
        if (! in_array($this->phaseNr, [3, 4, 5], true)) {
            return $messages;
        }

        $query = $projekt->forschungsfrage ?? null;
        if (! $query) {
            return $messages;
        }

        // Phase 5 needs more results to populate p5_treffer; P3/P4 need fewer for context
        $maxPerSource = $this->phaseNr === 5 ? 8 : 4;
        $sources = ['pubmed', 'arxiv', 'semantic'];

        $result = app(PaperSearchService::class)->search($query, $sources, $maxPerSource);

        if ($result['total'] === 0 || $result['context'] === '') {
            Log::info('PaperSearchService: keine Ergebnisse', [
                'projekt_id' => $this->projektId,
                'phase_nr' => $this->phaseNr,
                'query' => mb_substr($query, 0, 100),
            ]);

            return $messages;
        }

        Log::info('PaperSearchService: Papers als Kontext geladen', [
            'projekt_id' => $this->projektId,
            'phase_nr' => $this->phaseNr,
            'total' => $result['total'],
        ]);

        return [['role' => 'user', 'content' => $result['context']], ...$messages];
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
        if (empty($retrievedChunks)) {
            return $rawContent;
        }

        // Versuche JSON-Envelope zu parsen (für md_files Injection)
        $trimmed = trim($rawContent);
        if ($trimmed === '' || ! str_starts_with($trimmed, '{')) {
            return $rawContent;
        }

        try {
            $parsed = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $rawContent;
        }

        if (! is_array($parsed)) {
            return $rawContent;
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
}
