<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReviewRegistrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $userId) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $spamProbability = $this->assessSpamProbability($user);

        $threshold = (float) config('services.anthropic.spam_threshold', 0.80);
        if ($spamProbability >= $threshold) {
            $user->update(['status' => 'suspended']);
            Log::warning('Registration spam detected — account suspended', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $user->registration_ip,
                'spam_probability' => $spamProbability,
            ]);
        }
    }

    private function assessSpamProbability(User $user): float
    {
        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            return 0.0;
        }

        $prompt = "Bewerte die Spam-Wahrscheinlichkeit dieser Registrierung auf einer Skala von 0.0 bis 1.0.\n"
            ."Antworte NUR mit einer Zahl zwischen 0.0 und 1.0, nichts weiter.\n\n"
            ."E-Mail: {$user->email}\n"
            ."IP-Adresse: {$user->registration_ip}\n"
            ."Registrierungszeitpunkt: {$user->created_at}\n"
            ."Forschungsbereich: {$user->forschungsbereich}\n\n"
            .'Kriterien: zufällige Zeichenfolgen in E-Mail-Adressen, verdächtige Domains, '
            .'bekannte Spam-Muster, ungewöhnliche Kombinationen.';

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('services.anthropic.models.chat-agent', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => 10,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);

            if ($response->failed()) {
                Log::warning('ReviewRegistrationJob: Claude API error', [
                    'status' => $response->status(),
                ]);

                return 0.0;
            }

            $content = trim($response->json('content.0.text') ?? '0');

            return min(1.0, max(0.0, (float) $content));
        } catch (\Throwable $e) {
            Log::warning('ReviewRegistrationJob: Claude API call failed', ['error' => $e->getMessage()]);

            return 0.0;
        }
    }
}
