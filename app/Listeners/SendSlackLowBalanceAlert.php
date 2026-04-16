<?php

namespace App\Listeners;

use App\Events\WorkspaceLowBalance;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendSlackLowBalanceAlert
{
    public function handle(WorkspaceLowBalance $event): void
    {
        $token = config('services.slack.notifications.bot_user_oauth_token');
        $channel = config('services.slack.notifications.channel');

        if (! $token || ! $channel) {
            return;
        }

        $balance = number_format($event->balanceCents / 100, 2, ',', '.');

        try {
            Http::withToken($token)->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channel,
                'text' => "⚠️ Low Balance: Workspace \"{$event->workspace->name}\" — {$balance} € ({$event->thresholdPercent}% Schwelle unterschritten)",
            ]);
        } catch (\Throwable $e) {
            Log::warning('Slack Low-Balance-Alert fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }
}
