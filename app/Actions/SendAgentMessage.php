<?php

namespace App\Actions;

use App\Services\AgentDailyLimitExceededException;
use App\Services\InsufficientCreditsException;
use App\Services\LangdockAgentException;
use App\Services\LangdockAgentService;
use Illuminate\Support\Facades\Log;

/**
 * Sends a message to a configured Langdock agent.
 *
 * Wird von ProcessPhaseAgentJob für synchrone Worker-Agent-Aufrufe verwendet.
 * Chat-Requests laufen seit der Migration über ChatStreamController → StreamingAgentService (MCP-Streaming).
 */
class SendAgentMessage
{
    public function __construct(
        private readonly LangdockAgentService $agent,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{success: bool, content: string, raw?: array}
     */
    public function execute(string $configKey, array $messages, int $timeout = 120, array $context = []): array
    {
        try {
            $response = $this->agent->callByConfigKey($configKey, $messages, $timeout, $context);

            return [
                'success' => true,
                'content' => $response['content'],
                'raw' => $response['raw'],
            ];
        } catch (InsufficientCreditsException) {
            return ['success' => false, 'content' => __('Guthaben aufgebraucht. Bitte den Admin kontaktieren.')];
        } catch (AgentDailyLimitExceededException $e) {
            Log::warning('Agent daily limit exceeded', ['key' => $configKey, 'message' => $e->getMessage()]);

            return ['success' => false, 'content' => __('Tageslimit für diesen Agenten erreicht. Bitte morgen erneut versuchen.')];
        } catch (LangdockAgentException $e) {
            Log::error('Langdock config key error', ['key' => $configKey, 'error' => $e->getMessage()]);

            return ['success' => false, 'content' => __('Fehler bei der Verarbeitung. Bitte versuche es erneut.')];
        } catch (\Throwable $e) {
            Log::error('SendAgentMessage: unerwartete Exception', [
                'key' => $configKey,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'content' => __('Verbindung fehlgeschlagen. Bitte versuche es später erneut.')];
        }
    }
}
