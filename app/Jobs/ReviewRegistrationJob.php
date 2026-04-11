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

    public int $tries = 2;

    public function __construct(public readonly int $userId) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $spamProbability = $this->assessSpamProbability($user);

        if ($spamProbability >= 0.80) {
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
        $apiKey = config('services.langdock.api_key');
        $baseUrl = config('services.langdock.base_url');

        if (! $apiKey || ! $baseUrl) {
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
                ->withToken($apiKey)
                ->post($baseUrl, [
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 10,
                ]);

            $content = trim($response->json('choices.0.message.content') ?? '0');

            return min(1.0, max(0.0, (float) $content));
        } catch (\Throwable $e) {
            Log::warning('ReviewRegistrationJob: Langdock call failed', ['error' => $e->getMessage()]);

            return 0.0;
        }
    }
}
