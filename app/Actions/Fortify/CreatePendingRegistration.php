<?php

namespace App\Actions\Fortify;

use App\Mail\PendingRegistrationVerificationMail;
use App\Models\PendingRegistration;
use App\Models\RegistrationAttempt;
use App\Models\User;
use App\Services\ConfidenceScoreCalculator;
use App\Services\GeoIpService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreatePendingRegistration implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(
        private readonly GeoIpService $geoIp,
        private readonly ConfidenceScoreCalculator $scoreCalculator,
    ) {}

    public function create(array $input): User
    {
        $ip = request()->ip() ?? '';
        $userAgent = request()->userAgent();

        if (!empty($input['website'] ?? null)) {
            $this->logBlockedAttempt($ip, $userAgent, 'honeypot', $input['email'] ?? null, 0, []);
            throw ValidationException::withMessages(['email' => ['Registrierung fehlgeschlagen.']]);
        }

        $key = 'register:' . $ip;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->logBlockedAttempt($ip, $userAgent, 'rate_limit', $input['email'] ?? null, 0, []);
            throw ValidationException::withMessages(['email' => [__('Too many registration attempts. Please try again later.')]]);
        }
        RateLimiter::hit($key, 3600);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class),
                Rule::unique(PendingRegistration::class),
            ],
            'password' => $this->passwordRules(),
            'forschungsfrage' => ['required', 'string', 'max:2000'],
            'forschungsbereich' => ['required', 'string', Rule::in([
                'Gesundheit & Medizin', 'Psychologie & Sozialwissenschaften', 'Bildung & Pädagogik',
                'Informatik & Technologie', 'Wirtschaft & Management', 'Umwelt & Nachhaltigkeit', 'Sonstiges',
            ])],
            'erfahrung' => ['required', 'string', Rule::in([
                'Nein, das wäre mein erstes Mal', 'Ja, 1–2 Mal', 'Ja, regelmäßig',
            ])],
        ])->validate();

        $geo = $this->geoIp->lookup($ip);
        $countryCode = $geo['country_code'] ?? '';

        $scoreResult = $this->scoreCalculator->calculate($input, $ip, $countryCode);
        $score = $scoreResult['score'];
        $breakdown = $scoreResult['breakdown'];

        if ($score >= 80) {
            $this->logBlockedAttempt($ip, $userAgent, 'confidence_score', $input['email'] ?? null, $score, $breakdown);
            throw ValidationException::withMessages(['email' => ['Registrierung fehlgeschlagen.']]);
        }

        $pending = PendingRegistration::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'forschungsfrage' => $input['forschungsfrage'],
            'forschungsbereich' => $input['forschungsbereich'],
            'erfahrung' => $input['erfahrung'],
            'token' => Str::uuid()->toString(),
            'token_expires_at' => now()->addHours(24),
            'confidence_score' => $score,
            'score_breakdown' => $breakdown,
            'registration_ip' => $ip ?: null,
            'registration_country_code' => $countryCode ?: null,
            'registration_country_name' => $geo['country_name'] ?? null,
            'registration_city' => $geo['city'] ?? null,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
            'needs_review' => $score >= 40,
            'expires_at' => now()->addHours(48),
        ]);

        Mail::to($pending->email)->queue(new PendingRegistrationVerificationMail($pending));

        throw new HttpResponseException(
            redirect()->route('register')->with('status', 'verification-link-sent')
        );
    }

    private function logBlockedAttempt(string $ip, ?string $userAgent, string $reason, ?string $email, int $score, array $breakdown): void
    {
        $geo = $this->geoIp->lookup($ip);

        RegistrationAttempt::create([
            'id' => Str::uuid(),
            'ip' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
            'reason' => $reason,
            'email' => $email ? mb_substr($email, 0, 255) : null,
            'country_code' => $geo['country_code'] ?? null,
            'country_name' => $geo['country_name'] ?? null,
            'city' => $geo['city'] ?? null,
            'created_at' => now(),
        ]);
    }
}
