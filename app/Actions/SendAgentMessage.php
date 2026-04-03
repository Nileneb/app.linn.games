<?php

namespace App\Actions;

use App\Services\InsufficientCreditsException;
use App\Services\LangdockAgentException;
use App\Services\LangdockAgentService;
use Illuminate\Support\Facades\Log;

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
                'raw'     => $response['raw'],
            ];
        } catch (InsufficientCreditsException) {
            return ['success' => false, 'content' => __('Guthaben aufgebraucht. Bitte den Admin kontaktieren.')];
        } catch (LangdockAgentException $e) {
            Log::error('Langdock config key error', ['key' => $configKey, 'error' => $e->getMessage()]);
            return ['success' => false, 'content' => __('Fehler bei der Verarbeitung. Bitte versuche es erneut.')];
        } catch (\Throwable) {
            return ['success' => false, 'content' => __('Verbindung fehlgeschlagen. Bitte versuche es später erneut.')];
        }
    }
}
