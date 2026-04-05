<?php

namespace App\Jobs;

use App\Actions\SendAgentMessage;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Services\RetrieverService;
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

    public function __construct(
        private readonly string $projektId,
        private readonly int    $phaseNr,
        private readonly string $agentConfigKey,
        private readonly array  $messages,
        private readonly array  $context,
    ) {}

    public function handle(): void
    {
        $projekt = Projekt::findOrFail($this->projektId);

        $messages = $this->prependRetrieverContext($projekt, $this->messages);

        // Create pending result record
        $result = PhaseAgentResult::create([
            'projekt_id' => $this->projektId,
            'user_id' => $projekt->user_id,
            'phase_nr' => $this->phaseNr,
            'agent_config_key' => $this->agentConfigKey,
            'status' => 'pending',
        ]);

        try {
            $response = app(SendAgentMessage::class)->execute(
                $this->agentConfigKey,
                $messages,
                120, // HTTP timeout for the LLM call
                $this->context
            );

            if ($response['success']) {
                $result->markCompleted($response['content']);
                Log::info('Phase agent job completed', [
                    'projekt_id' => $this->projektId,
                    'phase_nr' => $this->phaseNr,
                    'agent_config_key' => $this->agentConfigKey,
                ]);
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
            $result->markFailed(__('Verarbeitung fehlgeschlagen: ') . $e->getMessage());
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
        $chunks    = $retriever->retrieve($query, $this->projektId);

        if (empty($chunks)) {
            return $messages;
        }

        $contextText = $retriever->formatAsContext($chunks);

        Log::info('Retriever: ' . count($chunks) . ' Chunks für Phase-Agent vorbereitet', [
            'projekt_id' => $this->projektId,
            'phase_nr'   => $this->phaseNr,
            'chunks'     => count($chunks),
        ]);

        return [['role' => 'user', 'content' => $contextText], ...$messages];
    }
}
