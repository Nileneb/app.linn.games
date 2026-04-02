<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerLangdockAgent implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $userId,
        public readonly string $projektId,
        public readonly string $eingabe,
    ) {}

    public function uniqueId(): string
    {
        return $this->projektId;
    }

    public function handle(): void
    {
        $webhook = \App\Models\Webhook::forUser($this->userId, 'recherche_start');
        $webhookUrl = $webhook?->callUrl() ?? config('services.langdock.webhook_url');

        $user = \App\Models\User::find($this->userId);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.langdock.api_key'),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($webhookUrl, [
            'user_id'     => $this->userId,
            'user_status' => $user?->status ?? 'trial',
            'projekt_id'  => $this->projektId,
            'eingabe'     => $this->eingabe,
        ]);

        if ($response->failed()) {
            Log::error('Langdock agent trigger failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'projekt_id' => $this->projektId,
            ]);

            $this->fail(new \RuntimeException("Langdock API returned {$response->status()}"));
        }
    }
}
