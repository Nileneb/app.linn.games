<?php

namespace App\Actions\Fortify;

use App\Jobs\ReviewRegistrationJob;
use App\Models\RegistrationAttempt;
use App\Models\User;
use App\Services\GeoIpService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $ip = request()->ip() ?? '';
        $userAgent = request()->userAgent();

        if (! empty($input['website'] ?? null)) {
            $this->logBlockedAttempt($ip, $userAgent, 'honeypot', $input['email'] ?? null);
            throw ValidationException::withMessages([
                'email' => ['Registrierung fehlgeschlagen.'],
            ]);
        }

        // Rate limiting: 5 registrations per IP per hour
        $key = 'register:'.$ip;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->logBlockedAttempt($ip, $userAgent, 'rate_limit', $input['email'] ?? null);
            throw ValidationException::withMessages([
                'email' => [__('Too many registration attempts. Please try again later.')],
            ]);
        }
        RateLimiter::hit($key, 3600); // 1 hour decay

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'forschungsfrage' => ['required', 'string', 'max:2000'],
            'forschungsbereich' => [
                'required',
                'string',
                Rule::in([
                    'Gesundheit & Medizin',
                    'Psychologie & Sozialwissenschaften',
                    'Bildung & Pädagogik',
                    'Informatik & Technologie',
                    'Wirtschaft & Management',
                    'Umwelt & Nachhaltigkeit',
                    'Sonstiges',
                ]),
            ],
            'erfahrung' => [
                'required',
                'string',
                Rule::in([
                    'Nein, das wäre mein erstes Mal',
                    'Ja, 1–2 Mal',
                    'Ja, regelmäßig',
                ]),
            ],
        ])->validate();

        $geo = app(GeoIpService::class)->lookup($ip);

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'status' => 'waitlisted',
            'forschungsfrage' => $input['forschungsfrage'],
            'forschungsbereich' => $input['forschungsbereich'],
            'erfahrung' => $input['erfahrung'],
            'registration_ip' => $ip,
            'registration_country_code' => $geo['country_code'] ?? null,
            'registration_country_name' => $geo['country_name'] ?? null,
            'registration_city' => $geo['city'] ?? null,
        ]);

        // Spam-Review im Hintergrund — kein Einfluss auf Registrierungs-Response
        ReviewRegistrationJob::dispatch($user->id)->delay(now()->addSeconds(10));

        return $user;
    }

    private function logBlockedAttempt(string $ip, ?string $userAgent, string $reason, ?string $email): void
    {
        $geo = app(GeoIpService::class)->lookup($ip);

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
