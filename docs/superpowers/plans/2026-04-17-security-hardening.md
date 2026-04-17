# Security Hardening (Prio 1–4) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ersetze direkte User-Anlage durch einen E-Mail-verifizierten PendingRegistration-Flow, ergänzt durch ein erweiterbares Confidence-Score-System (JS-Timing, Timezone-Mismatch, Tor-Detection, Disposable-Email).

**Architecture:** `CreateNewUser` wird zu `CreatePendingRegistration` — wirft nach Erstellung eines `PendingRegistration`-Datensatzes eine `HttpResponseException` (Redirect), damit Fortify den User nicht einloggt. Echte User-Anlage erst nach E-Mail-Bestätigung via `VerifyPendingRegistrationController`. Score wird in `confidence_score` + `score_breakdown` (JSON) auf `PendingRegistration` persistiert.

**Tech Stack:** Laravel 12, Fortify, Filament 4, Pest, Redis (predis), `propaganistas/laravel-disposable-email`

---

## File Map

| Aktion | Datei | Zweck |
|---|---|---|
| Create | `database/migrations/[date]_create_pending_registrations_table.php` | Schema |
| Create | `app/Models/PendingRegistration.php` | Eloquent Model |
| Create | `app/Services/TorDetectionService.php` | Redis-basierte Tor-IP-Prüfung |
| Create | `app/Services/ConfidenceScoreCalculator.php` | Score-Berechnung (alle Signale) |
| Create | `app/Console/Commands/SyncTorNodes.php` | Tor-Liste aus torproject.org cachen |
| Create | `app/Console/Commands/PrunePendingRegistrations.php` | Abgelaufene PendingRegistrations löschen |
| Modify | `app/Actions/Fortify/CreateNewUser.php` → rename to `CreatePendingRegistration.php` | Neuer Action-Name |
| Create | `app/Actions/Fortify/CreatePendingRegistration.php` | Ersetze CreateNewUser |
| Create | `app/Mail/PendingRegistrationVerificationMail.php` | Verifikations-Mail |
| Create | `resources/views/emails/pending-registration-verification.blade.php` | Mail-Template |
| Create | `app/Http/Controllers/VerifyPendingRegistrationController.php` | Token-Verifikation → User anlegen |
| Modify | `routes/web.php` | Verifikations-Route hinzufügen |
| Modify | `app/Providers/FortifyServiceProvider.php` | Binding auf CreatePendingRegistration |
| Modify | `resources/views/livewire/auth/register.blade.php` | JS-Signale + Hidden Fields |
| Modify | `routes/console.php` | Scheduler + Commands registrieren |
| Create | `app/Filament/Resources/PendingRegistrationResource.php` | Filament-Resource |
| Create | `app/Filament/Resources/PendingRegistrationResource/Pages/ListPendingRegistrations.php` | List-Page |
| Modify | `tests/Feature/Auth/RegistrationTest.php` | Tests auf neuen Flow anpassen |
| Modify | `tests/Feature/Auth/BetaApplicationTest.php` | Tests auf neuen Flow anpassen |
| Create | `tests/Feature/Auth/PendingRegistrationVerificationTest.php` | Verifikations-Flow testen |
| Create | `tests/Feature/Security/ConfidenceScoreTest.php` | Score-Kalkulator testen |
| Create | `tests/Feature/Security/TorDetectionTest.php` | TorDetectionService testen |

---

## Task 1: Package installieren + Migration erstellen

**Files:**
- Create: `database/migrations/[date]_create_pending_registrations_table.php`

- [ ] **Step 1: Package installieren**

```bash
cd /home/nileneb/Desktop/WebDev/app.linn.games
composer require propaganistas/laravel-disposable-email
```

Expected: Package in `composer.json`, Autoload updated.

- [ ] **Step 2: Disposable-Email-Liste initial laden**

```bash
php artisan disposable:update
```

Expected: `Disposable email domains updated.`

- [ ] **Step 3: Migration erstellen**

```bash
php artisan make:migration create_pending_registrations_table
```

- [ ] **Step 4: Migration befüllen**

Öffne die neu erstellte Migration und ersetze den Inhalt mit:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('forschungsfrage');
            $table->string('forschungsbereich');
            $table->string('erfahrung');
            $table->uuid('token')->unique();
            $table->timestamp('token_expires_at');
            $table->integer('confidence_score')->default(0);
            $table->json('score_breakdown')->default('{"timing":0,"timezone":0,"tor":0,"disposable":0}');
            $table->string('registration_ip')->nullable();
            $table->string('registration_country_code', 2)->nullable();
            $table->string('registration_country_name')->nullable();
            $table->string('registration_city')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('needs_review')->default(false);
            $table->enum('status', ['pending_email', 'verified', 'rejected'])->default('pending_email');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
```

- [ ] **Step 5: Migration ausführen**

```bash
php artisan migrate
```

Expected: `pending_registrations` Tabelle angelegt.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/ composer.json composer.lock
git commit -m "feat: add pending_registrations migration + disposable-email package"
```

---

## Task 2: PendingRegistration Model

**Files:**
- Create: `app/Models/PendingRegistration.php`

- [ ] **Step 1: Failing test schreiben**

Erstelle `tests/Feature/Security/PendingRegistrationModelTest.php`:

```php
<?php

use App\Models\PendingRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('pending registration kann angelegt werden', function () {
    $pending = PendingRegistration::create([
        'id' => Str::uuid(),
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'forschungsfrage' => 'Frage',
        'forschungsbereich' => 'Sonstiges',
        'erfahrung' => 'Ja, 1–2 Mal',
        'token' => Str::uuid(),
        'token_expires_at' => now()->addHours(24),
        'expires_at' => now()->addHours(48),
    ]);

    expect($pending->confidence_score)->toBe(0)
        ->and($pending->status)->toBe('pending_email')
        ->and($pending->needs_review)->toBeFalse();
});

test('isExpired gibt true zurück wenn token_expires_at vergangen', function () {
    $pending = PendingRegistration::create([
        'id' => Str::uuid(),
        'name' => 'Test',
        'email' => 'expired@example.com',
        'password' => bcrypt('password'),
        'forschungsfrage' => 'Frage',
        'forschungsbereich' => 'Sonstiges',
        'erfahrung' => 'Ja, 1–2 Mal',
        'token' => Str::uuid(),
        'token_expires_at' => now()->subMinute(),
        'expires_at' => now()->addHours(48),
    ]);

    expect($pending->isExpired())->toBeTrue();
});
```

- [ ] **Step 2: Test ausführen (muss fehlschlagen)**

```bash
./vendor/bin/pest tests/Feature/Security/PendingRegistrationModelTest.php
```

Expected: FAIL — `App\Models\PendingRegistration` not found.

- [ ] **Step 3: Model erstellen**

Erstelle `app/Models/PendingRegistration.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'email',
        'password',
        'forschungsfrage',
        'forschungsbereich',
        'erfahrung',
        'token',
        'token_expires_at',
        'confidence_score',
        'score_breakdown',
        'registration_ip',
        'registration_country_code',
        'registration_country_name',
        'registration_city',
        'user_agent',
        'needs_review',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'expires_at' => 'datetime',
            'score_breakdown' => 'array',
            'needs_review' => 'boolean',
            'confidence_score' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at->isPast();
    }
}
```

- [ ] **Step 4: Test ausführen (muss bestehen)**

```bash
./vendor/bin/pest tests/Feature/Security/PendingRegistrationModelTest.php
```

Expected: 2 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Models/PendingRegistration.php tests/Feature/Security/PendingRegistrationModelTest.php
git commit -m "feat: add PendingRegistration model"
```

---

## Task 3: TorDetectionService + SyncTorNodes Command

**Files:**
- Create: `app/Services/TorDetectionService.php`
- Create: `app/Console/Commands/SyncTorNodes.php`
- Create: `tests/Feature/Security/TorDetectionTest.php`

- [ ] **Step 1: Failing tests schreiben**

Erstelle `tests/Feature/Security/TorDetectionTest.php`:

```php
<?php

use App\Services\TorDetectionService;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Redis::del('security:tor_nodes');
});

test('isKnownTorOrVpnIp gibt false zurück wenn Redis-Set leer', function () {
    $service = app(TorDetectionService::class);

    expect($service->isKnownTorOrVpnIp('1.2.3.4'))->toBeFalse();
});

test('isKnownTorOrVpnIp erkennt bekannte Tor-IP', function () {
    Redis::sadd('security:tor_nodes', '185.220.101.144');

    $service = app(TorDetectionService::class);

    expect($service->isKnownTorOrVpnIp('185.220.101.144'))->toBeTrue()
        ->and($service->isKnownTorOrVpnIp('1.2.3.4'))->toBeFalse();
});

test('isKnownTorOrVpnIp gibt false zurück bei Redis-Fehler', function () {
    // Simuliere Fehler durch falschen Typ im Key
    Redis::set('security:tor_nodes', 'not-a-set');

    $service = app(TorDetectionService::class);

    expect($service->isKnownTorOrVpnIp('185.220.101.144'))->toBeFalse();
});
```

- [ ] **Step 2: Tests ausführen (müssen fehlschlagen)**

```bash
./vendor/bin/pest tests/Feature/Security/TorDetectionTest.php
```

Expected: FAIL — `TorDetectionService` not found.

- [ ] **Step 3: TorDetectionService erstellen**

Erstelle `app/Services/TorDetectionService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class TorDetectionService
{
    private const REDIS_KEY = 'security:tor_nodes';

    public function isKnownTorOrVpnIp(string $ip): bool
    {
        try {
            return (bool) Redis::sismember(self::REDIS_KEY, $ip);
        } catch (\Throwable) {
            return false;
        }
    }
}
```

- [ ] **Step 4: Tests ausführen (müssen bestehen)**

```bash
./vendor/bin/pest tests/Feature/Security/TorDetectionTest.php
```

Expected: 3 passed.

- [ ] **Step 5: SyncTorNodes Command erstellen**

Erstelle `app/Console/Commands/SyncTorNodes.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class SyncTorNodes extends Command
{
    protected $signature = 'security:sync-tor-nodes';

    protected $description = 'Synchronisiert Tor-Exit-Node-Liste von torproject.org in Redis';

    public function handle(): int
    {
        $response = Http::timeout(30)->get('https://check.torproject.org/torbulkexitlist');

        if ($response->failed()) {
            $this->error("Tor-Node-Liste konnte nicht geladen werden: HTTP {$response->status()}");
            return self::FAILURE;
        }

        $ips = array_filter(
            array_map('trim', explode("\n", $response->body())),
            fn (string $line) => $line !== '' && !str_starts_with($line, '#')
        );

        if (empty($ips)) {
            $this->warn('Tor-Node-Liste ist leer — Abbruch ohne Redis-Update.');
            return self::FAILURE;
        }

        $key = 'security:tor_nodes';
        Redis::del($key);
        Redis::sadd($key, ...$ips);
        Redis::expire($key, 6 * 3600);

        $this->info(sprintf('Tor-Node-Liste synchronisiert: %d IPs importiert.', count($ips)));

        return self::SUCCESS;
    }
}
```

- [ ] **Step 6: Command manuell testen**

```bash
php artisan security:sync-tor-nodes
```

Expected: `Tor-Node-Liste synchronisiert: XXXX IPs importiert.`

- [ ] **Step 7: Commit**

```bash
git add app/Services/TorDetectionService.php app/Console/Commands/SyncTorNodes.php tests/Feature/Security/TorDetectionTest.php
git commit -m "feat: TorDetectionService + security:sync-tor-nodes command"
```

---

## Task 4: ConfidenceScoreCalculator

**Files:**
- Create: `app/Services/ConfidenceScoreCalculator.php`
- Create: `tests/Feature/Security/ConfidenceScoreTest.php`

- [ ] **Step 1: Failing tests schreiben**

Erstelle `tests/Feature/Security/ConfidenceScoreTest.php`:

```php
<?php

use App\Services\ConfidenceScoreCalculator;
use App\Services\TorDetectionService;
use Illuminate\Support\Facades\Redis;

beforeEach(fn () => Redis::del('security:tor_nodes'));

function baseInput(array $overrides = []): array
{
    return array_merge([
        '_timing' => 5000,
        '_tz' => 'Europe/Berlin',
        'email' => 'test@example.com',
    ], $overrides);
}

test('sauberer nutzer erhält score 0', function () {
    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(baseInput(), '127.0.0.1', 'DE');

    expect($result['score'])->toBe(0)
        ->and($result['breakdown']['timing'])->toBe(0)
        ->and($result['breakdown']['tor'])->toBe(0)
        ->and($result['breakdown']['disposable'])->toBe(0);
});

test('timing unter 2000ms ergibt +50', function () {
    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(baseInput(['_timing' => 1500]), '127.0.0.1', 'DE');

    expect($result['breakdown']['timing'])->toBe(50)
        ->and($result['score'])->toBeGreaterThanOrEqual(50);
});

test('fehlendes timing-feld ergibt +50 (bot ohne js)', function () {
    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(['email' => 'test@example.com'], '127.0.0.1', 'DE');

    expect($result['breakdown']['timing'])->toBe(50);
});

test('timezone-mismatch ergibt +20', function () {
    $calc = app(ConfidenceScoreCalculator::class);
    // Timezone America/New_York passt nicht zu DE
    $result = $calc->calculate(baseInput(['_tz' => 'America/New_York']), '127.0.0.1', 'DE');

    expect($result['breakdown']['timezone'])->toBe(20);
});

test('bekannte tor-ip ergibt +15', function () {
    Redis::sadd('security:tor_nodes', '185.220.101.144');

    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(baseInput(), '185.220.101.144', 'DE');

    expect($result['breakdown']['tor'])->toBe(15);
});

test('disposable email ergibt +40', function () {
    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(baseInput(['email' => 'test@mailinator.com']), '127.0.0.1', 'DE');

    expect($result['breakdown']['disposable'])->toBe(40);
});

test('kombinierter score summiert alle beiträge', function () {
    Redis::sadd('security:tor_nodes', '185.220.101.144');

    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(
        baseInput(['_timing' => 500, '_tz' => 'America/New_York', 'email' => 'test@mailinator.com']),
        '185.220.101.144',
        'DE'
    );

    // 50 (timing) + 20 (tz) + 15 (tor) + 40 (disposable) = 125
    expect($result['score'])->toBe(125);
});
```

- [ ] **Step 2: Tests ausführen (müssen fehlschlagen)**

```bash
./vendor/bin/pest tests/Feature/Security/ConfidenceScoreTest.php
```

Expected: FAIL — `ConfidenceScoreCalculator` not found.

- [ ] **Step 3: ConfidenceScoreCalculator erstellen**

Erstelle `app/Services/ConfidenceScoreCalculator.php`:

```php
<?php

namespace App\Services;

class ConfidenceScoreCalculator
{
    public function __construct(private readonly TorDetectionService $torDetection) {}

    /**
     * @return array{score: int, breakdown: array{timing: int, timezone: int, tor: int, disposable: int}}
     */
    public function calculate(array $input, string $ip, string $geoCountryCode): array
    {
        $breakdown = ['timing' => 0, 'timezone' => 0, 'tor' => 0, 'disposable' => 0];

        $timing = isset($input['_timing']) ? (int) $input['_timing'] : 0;
        if ($timing < 2000) {
            $breakdown['timing'] = 50;
        }

        $tz = trim($input['_tz'] ?? '');
        if ($tz && $geoCountryCode && !$this->timezoneMatchesCountry($tz, $geoCountryCode)) {
            $breakdown['timezone'] = 20;
        }

        if ($this->torDetection->isKnownTorOrVpnIp($ip)) {
            $breakdown['tor'] = 15;
        }

        $email = trim($input['email'] ?? '');
        if ($email && $this->isDisposableEmail($email)) {
            $breakdown['disposable'] = 40;
        }

        return [
            'score' => array_sum($breakdown),
            'breakdown' => $breakdown,
        ];
    }

    private function timezoneMatchesCountry(string $timezone, string $countryCode): bool
    {
        try {
            $zones = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, strtoupper($countryCode));
            return in_array($timezone, $zones, true);
        } catch (\Throwable) {
            return true;
        }
    }

    private function isDisposableEmail(string $email): bool
    {
        return !validator(['email' => $email], ['email' => 'indisposable'])->passes();
    }
}
```

- [ ] **Step 4: Tests ausführen (müssen bestehen)**

```bash
./vendor/bin/pest tests/Feature/Security/ConfidenceScoreTest.php
```

Expected: 7 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ConfidenceScoreCalculator.php tests/Feature/Security/ConfidenceScoreTest.php
git commit -m "feat: ConfidenceScoreCalculator with timing/timezone/tor/disposable signals"
```

---

## Task 5: CreatePendingRegistration Action

**Files:**
- Create: `app/Actions/Fortify/CreatePendingRegistration.php`
- Modify: `app/Providers/FortifyServiceProvider.php`

- [ ] **Step 1: Action erstellen**

Erstelle `app/Actions/Fortify/CreatePendingRegistration.php`:

```php
<?php

namespace App\Actions\Fortify;

use App\Models\PendingRegistration;
use App\Models\RegistrationAttempt;
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
use App\Mail\PendingRegistrationVerificationMail;
use App\Models\User;

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
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class), Rule::unique(PendingRegistration::class)],
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
            'token' => Str::uuid(),
            'token_expires_at' => now()->addHours(24),
            'confidence_score' => $score,
            'score_breakdown' => $breakdown,
            'registration_ip' => $ip,
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
```

- [ ] **Step 2: FortifyServiceProvider anpassen**

In `app/Providers/FortifyServiceProvider.php`, ändere:

```php
// Alt:
use App\Actions\Fortify\CreateNewUser;
// ...
Fortify::createUsersUsing(CreateNewUser::class);

// Neu:
use App\Actions\Fortify\CreatePendingRegistration;
// ...
Fortify::createUsersUsing(CreatePendingRegistration::class);
```

- [ ] **Step 3: Bestehende Tests auf Rot prüfen**

```bash
./vendor/bin/pest tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/BetaApplicationTest.php
```

Expected: Tests schlagen fehl (User wird nicht mehr angelegt, kein Login nach Register). Das ist korrekt — wird in Task 11 gefixt.

- [ ] **Step 4: Commit**

```bash
git add app/Actions/Fortify/CreatePendingRegistration.php app/Providers/FortifyServiceProvider.php
git commit -m "feat: CreatePendingRegistration replaces CreateNewUser with confidence score + pending flow"
```

---

## Task 6: Verifikations-Mail + Template

**Files:**
- Create: `app/Mail/PendingRegistrationVerificationMail.php`
- Create: `resources/views/emails/pending-registration-verification.blade.php`

- [ ] **Step 1: Mailable erstellen**

Erstelle `app/Mail/PendingRegistrationVerificationMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\PendingRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PendingRegistrationVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly PendingRegistration $pending) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'E-Mail-Adresse bestätigen – app.linn.games');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pending-registration-verification');
    }
}
```

- [ ] **Step 2: Mail-Template erstellen**

Erstelle `resources/views/emails/pending-registration-verification.blade.php`:

```blade
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #1a1a1a; max-width: 600px; margin: 0 auto; padding: 24px; }
        .btn { display: inline-block; background: #1a1a1a; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; margin: 24px 0; }
        .note { font-size: 0.85em; color: #666; }
    </style>
</head>
<body>
    <h2>Hallo {{ $pending->name }},</h2>
    <p>bitte bestätige deine E-Mail-Adresse, um deine Registrierung bei <strong>app.linn.games</strong> abzuschließen.</p>
    <p>Nach der Bestätigung wird dein Konto auf die Warteliste gesetzt und manuell freigeschaltet.</p>

    <a href="{{ route('register.verify', $pending->token) }}" class="btn">
        E-Mail-Adresse bestätigen
    </a>

    <p class="note">
        Dieser Link ist 24 Stunden gültig. Falls du dich nicht registriert hast, kannst du diese E-Mail ignorieren.
    </p>
    <p class="note">
        Direktlink: {{ route('register.verify', $pending->token) }}
    </p>
</body>
</html>
```

- [ ] **Step 3: Commit**

```bash
git add app/Mail/PendingRegistrationVerificationMail.php resources/views/emails/pending-registration-verification.blade.php
git commit -m "feat: PendingRegistrationVerificationMail + template"
```

---

## Task 7: VerifyPendingRegistrationController + Route

**Files:**
- Create: `app/Http/Controllers/VerifyPendingRegistrationController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Controller erstellen**

Erstelle `app/Http/Controllers/VerifyPendingRegistrationController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Jobs\ReviewRegistrationJob;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class VerifyPendingRegistrationController extends Controller
{
    public function __invoke(string $token): RedirectResponse
    {
        $pending = PendingRegistration::where('token', $token)
            ->where('status', 'pending_email')
            ->first();

        if (!$pending) {
            return redirect()->route('register')
                ->withErrors(['email' => 'Der Verifikationslink ist ungültig.']);
        }

        if ($pending->isExpired()) {
            $pending->delete();
            return redirect()->route('register')
                ->withErrors(['email' => 'Der Verifikationslink ist abgelaufen. Bitte registriere dich erneut.']);
        }

        // forceFill + save() umgeht den 'hashed'-Cast — Passwort ist bereits gehasht
        $user = new User();
        $user->forceFill([
            'name' => $pending->name,
            'email' => $pending->email,
            'password' => $pending->password,
            'status' => 'waitlisted',
            'forschungsfrage' => $pending->forschungsfrage,
            'forschungsbereich' => $pending->forschungsbereich,
            'erfahrung' => $pending->erfahrung,
            'registration_ip' => $pending->registration_ip,
            'registration_country_code' => $pending->registration_country_code,
            'registration_country_name' => $pending->registration_country_name,
            'registration_city' => $pending->registration_city,
        ])->save();

        ReviewRegistrationJob::dispatch($user->id)->delay(now()->addSeconds(10));

        $pending->delete();

        return redirect()->route('login')
            ->with('status', 'email-verified');
    }
}
```

- [ ] **Step 2: Route hinzufügen**

In `routes/web.php`, füge nach der `pending-approval`-Route ein:

```php
Route::get('/register/verify/{token}', \App\Http\Controllers\VerifyPendingRegistrationController::class)
    ->name('register.verify')
    ->middleware('guest');
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/VerifyPendingRegistrationController.php routes/web.php
git commit -m "feat: VerifyPendingRegistrationController + register.verify route"
```

---

## Task 8: JS-Signale im Registrierungsformular

**Files:**
- Modify: `resources/views/livewire/auth/register.blade.php`

- [ ] **Step 1: Hidden Fields + JS zum Formular hinzufügen**

In `resources/views/livewire/auth/register.blade.php`, füge direkt vor dem schließenden `</form>`-Tag ein:

```blade
            {{-- Bot-Detection: JS-Signale --}}
            <input type="hidden" name="_timing" id="_timing" value="0">
            <input type="hidden" name="_tz" id="_tz" value="">
```

Füge direkt vor dem schließenden `</div>` der Seite (nach `</form>`) ein:

```blade
        <script>
            (function () {
                var _start = Date.now();
                var form = document.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function () {
                        var timingEl = document.getElementById('_timing');
                        var tzEl = document.getElementById('_tz');
                        if (timingEl) timingEl.value = Date.now() - _start;
                        if (tzEl) {
                            try { tzEl.value = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) {}
                        }
                    });
                }
            })();
        </script>
```

- [ ] **Step 2: Status-Message für "check-your-email" anzeigen**

In `resources/views/livewire/auth/register.blade.php`, prüfe ob `<x-auth-session-status>` bereits vorhanden ist (Zeile ~17). Falls ja, ist die Session-Status-Anzeige bereits aktiv — keine Änderung nötig, da `session('status') === 'verification-link-sent'` angezeigt wird.

Falls die Status-Komponente fehlt, füge vor dem `<form>`-Tag ein:

```blade
        @if (session('status') === 'verification-link-sent')
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800/50 dark:bg-green-950/30 dark:text-green-300">
                Wir haben dir einen Bestätigungslink geschickt. Bitte prüfe dein Postfach.
            </div>
        @endif
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/auth/register.blade.php
git commit -m "feat: add JS timing/timezone signals to registration form"
```

---

## Task 9: Prune Command + Scheduler

**Files:**
- Create: `app/Console/Commands/PrunePendingRegistrations.php`
- Modify: `routes/console.php`

- [ ] **Step 1: PrunePendingRegistrations Command erstellen**

Erstelle `app/Console/Commands/PrunePendingRegistrations.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\PendingRegistration;
use Illuminate\Console\Command;

class PrunePendingRegistrations extends Command
{
    protected $signature = 'security:prune-pending-registrations';

    protected $description = 'Löscht abgelaufene PendingRegistration-Datensätze';

    public function handle(): int
    {
        $deleted = PendingRegistration::where('expires_at', '<', now())->delete();

        $this->info("Abgelaufene PendingRegistrations bereinigt: {$deleted} Datensätze gelöscht.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Scheduler und Commands in console.php registrieren**

In `routes/console.php`, füge am Ende der `Artisan::addCommands`-Liste hinzu:

```php
Artisan::addCommands([
    // ... bestehende Commands ...
    \App\Console\Commands\SyncTorNodes::class,
    \App\Console\Commands\PrunePendingRegistrations::class,
]);
```

Und im Scheduler-Block:

```php
Schedule::command('security:sync-tor-nodes')->everySixHours();
Schedule::command('disposable:update')->weekly();
Schedule::command('security:prune-pending-registrations')->daily();
```

- [ ] **Step 3: Command testen**

```bash
php artisan security:prune-pending-registrations
```

Expected: `Abgelaufene PendingRegistrations bereinigt: 0 Datensätze gelöscht.`

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/PrunePendingRegistrations.php routes/console.php
git commit -m "feat: prune-pending-registrations command + scheduler entries"
```

---

## Task 10: Filament PendingRegistrationResource

**Files:**
- Create: `app/Filament/Resources/PendingRegistrationResource.php`
- Create: `app/Filament/Resources/PendingRegistrationResource/Pages/ListPendingRegistrations.php`

- [ ] **Step 1: List-Page erstellen**

Erstelle `app/Filament/Resources/PendingRegistrationResource/Pages/ListPendingRegistrations.php`:

```php
<?php

namespace App\Filament\Resources\PendingRegistrationResource\Pages;

use App\Filament\Resources\PendingRegistrationResource;
use Filament\Resources\Pages\ListRecords;

class ListPendingRegistrations extends ListRecords
{
    protected static string $resource = PendingRegistrationResource::class;
}
```

- [ ] **Step 2: Resource erstellen**

Erstelle `app/Filament/Resources/PendingRegistrationResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PendingRegistrationResource\Pages\ListPendingRegistrations;
use App\Models\PendingRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class PendingRegistrationResource extends Resource
{
    protected static ?string $model = PendingRegistration::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationLabel = 'Pending Registrierungen';

    protected static \UnitEnum|string|null $navigationGroup = 'Sicherheit';

    protected static ?int $navigationSort = 11;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return Schema::hasTable('pending_registrations');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable(),
                Tables\Columns\TextColumn::make('confidence_score')
                    ->label('Score')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 80 => 'danger',
                        $state >= 40 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\IconColumn::make('needs_review')
                    ->label('Review nötig')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending_email' => 'warning',
                        'verified' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('registration_ip')
                    ->label('IP')
                    ->searchable(),
                Tables\Columns\TextColumn::make('registration_country_name')
                    ->label('Land'),
                Tables\Columns\TextColumn::make('token_expires_at')
                    ->label('Link läuft ab')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('needs_review')
                    ->label('Review nötig'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_email' => 'Pending',
                        'verified' => 'Verifiziert',
                        'rejected' => 'Abgelehnt',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPendingRegistrations::route('/'),
        ];
    }
}
```

- [ ] **Step 3: Filament Admin öffnen und prüfen**

```bash
php artisan serve
```

Im Browser `http://localhost:8000/admin` öffnen → Sicherheit → "Pending Registrierungen" muss erscheinen.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/PendingRegistrationResource.php app/Filament/Resources/PendingRegistrationResource/Pages/
git commit -m "feat: Filament PendingRegistrationResource with score badge + review filter"
```

---

## Task 11: Bestehende Tests aktualisieren

**Files:**
- Modify: `tests/Feature/Auth/RegistrationTest.php`
- Modify: `tests/Feature/Auth/BetaApplicationTest.php`

- [ ] **Step 1: RegistrationTest.php aktualisieren**

Ersetze den Inhalt von `tests/Feature/Auth/RegistrationTest.php`:

```php
<?php

use App\Models\PendingRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(fn () => RateLimiter::clear('register:127.0.0.1'));

test('registration screen can be rendered', function () {
    $this->get(route('register'))->assertStatus(200);
});

test('registrierung erstellt pending registration und kein user', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'forschungsfrage' => 'Welche Auswirkungen hat KI auf den Bildungsbereich?',
        'forschungsbereich' => 'Bildung & Pädagogik',
        'erfahrung' => 'Ja, 1–2 Mal',
    ]);

    $response->assertRedirect(route('register'))
        ->assertSessionHas('status', 'verification-link-sent');

    $this->assertGuest();
    expect(PendingRegistration::where('email', 'test@example.com')->exists())->toBeTrue();
    expect(\App\Models\User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('registrierung schlägt fehl wenn name fehlt', function () {
    $this->post(route('register.store'), [
        'email' => 'missing@name.de',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('name');

    $this->assertGuest();
});

test('registrierung schlägt fehl bei ungültiger email', function () {
    $this->post(route('register.store'), [
        'name' => 'Kein Valid',
        'email' => 'kein-gueltiges-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');
});

test('registrierung schlägt fehl bei bereits genutzter email', function () {
    \App\Models\User::factory()->withoutTwoFactor()->create(['email' => 'doppelt@example.de']);

    $this->post(route('register.store'), [
        'name' => 'Zweiter',
        'email' => 'doppelt@example.de',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');
});

test('registrierung schlägt fehl bei nicht übereinstimmenden passwörtern', function () {
    $this->post(route('register.store'), [
        'name' => 'Kein Match',
        'email' => 'nomatch@example.de',
        'password' => 'password',
        'password_confirmation' => 'anders123',
    ])->assertSessionHasErrors('password');
});
```

- [ ] **Step 2: BetaApplicationTest.php aktualisieren**

Ersetze den Inhalt von `tests/Feature/Auth/BetaApplicationTest.php`:

```php
<?php

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(fn () => RateLimiter::clear('register:127.0.0.1'));

$validPayload = fn () => [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => 'password',
    'password_confirmation' => 'password',
    'forschungsfrage' => 'Welche Auswirkungen hat digitale Bildung auf Schulergebnisse?',
    'forschungsbereich' => 'Bildung & Pädagogik',
    'erfahrung' => 'Ja, 1–2 Mal',
];

test('nutzer kann sich registrieren und erhält pending-registration mit verification-link-sent status', function () use ($validPayload) {
    $response = $this->post(route('register.store'), $validPayload());

    $response->assertRedirect(route('register'))
        ->assertSessionHas('status', 'verification-link-sent');

    $pending = PendingRegistration::where('email', 'test@example.com')->first();

    expect($pending)->not->toBeNull()
        ->and($pending->forschungsfrage)->toBe('Welche Auswirkungen hat digitale Bildung auf Schulergebnisse?')
        ->and($pending->forschungsbereich)->toBe('Bildung & Pädagogik')
        ->and($pending->status)->toBe('pending_email');
});

test('registrierung schlägt fehl wenn forschungsfrage fehlt', function () use ($validPayload) {
    $payload = $validPayload();
    unset($payload['forschungsfrage']);

    $this->post(route('register.store'), $payload)->assertSessionHasErrors('forschungsfrage');
});

test('registrierung schlägt fehl bei ungültigem forschungsbereich', function () use ($validPayload) {
    $payload = $validPayload();
    $payload['forschungsbereich'] = 'Ungültiger Bereich';

    $this->post(route('register.store'), $payload)->assertSessionHasErrors('forschungsbereich');
});

test('registrierung schlägt fehl wenn erfahrung fehlt', function () use ($validPayload) {
    $payload = $validPayload();
    unset($payload['erfahrung']);

    $this->post(route('register.store'), $payload)->assertSessionHasErrors('erfahrung');
});

test('admin kann waitlisted nutzer freischalten und status wird trial', function () {
    $waitlisted = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);
    $waitlisted->update(['status' => 'trial']);

    expect($waitlisted->fresh()->status)->toBe('trial');
});

test('isWaitlisted gibt true zurück für waitlisted nutzer', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);

    expect($user->isWaitlisted())->toBeTrue()
        ->and($user->isActive())->toBeFalse();
});

test('waitlisted nutzer wird durch middleware blockiert', function () {
    $user = User::factory()->withoutTwoFactor()->create(['status' => 'waitlisted']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('pending-approval'));
});
```

- [ ] **Step 3: Alle Tests ausführen**

```bash
./vendor/bin/pest tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/BetaApplicationTest.php
```

Expected: Alle Tests grün.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/BetaApplicationTest.php
git commit -m "test: update registration tests for PendingRegistration flow"
```

---

## Task 12: Verifikations-Flow Tests

**Files:**
- Create: `tests/Feature/Auth/PendingRegistrationVerificationTest.php`

- [ ] **Step 1: Tests schreiben**

Erstelle `tests/Feature/Auth/PendingRegistrationVerificationTest.php`:

```php
<?php

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makePending(array $overrides = []): PendingRegistration
{
    return PendingRegistration::create(array_merge([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'forschungsfrage' => 'Forschungsfrage',
        'forschungsbereich' => 'Sonstiges',
        'erfahrung' => 'Ja, 1–2 Mal',
        'token' => Str::uuid()->toString(),
        'token_expires_at' => now()->addHours(24),
        'expires_at' => now()->addHours(48),
        'status' => 'pending_email',
    ], $overrides));
}

test('gültiger token erstellt user und leitet zu login weiter', function () {
    $pending = makePending();

    $this->get(route('register.verify', $pending->token))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'email-verified');

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'test@example.com')->first()->status)->toBe('waitlisted')
        ->and(PendingRegistration::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('ungültiger token leitet zu register mit fehler weiter', function () {
    $this->get(route('register.verify', Str::uuid()))
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');
});

test('abgelaufener token löscht pending und zeigt fehler', function () {
    $pending = makePending(['token_expires_at' => now()->subMinute()]);

    $this->get(route('register.verify', $pending->token))
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');

    expect(PendingRegistration::find($pending->id))->toBeNull();
});

test('bereits verwendeter token (status != pending_email) gibt fehler', function () {
    $pending = makePending(['status' => 'verified']);

    $this->get(route('register.verify', $pending->token))
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');
});
```

- [ ] **Step 2: Tests ausführen**

```bash
./vendor/bin/pest tests/Feature/Auth/PendingRegistrationVerificationTest.php
```

Expected: 4 passed.

- [ ] **Step 3: Alle Security-Tests ausführen**

```bash
./vendor/bin/pest tests/Feature/Security/ tests/Feature/Auth/
```

Expected: Alle grün.

- [ ] **Step 4: Commit + Push**

```bash
git add tests/Feature/Auth/PendingRegistrationVerificationTest.php
git commit -m "test: PendingRegistration verification flow tests"
git push origin main
```

---

## Task 13: GitHub Sub-Issue für Prio 5 + Issue #215 kommentieren

- [ ] **Step 1: Sub-Issue für Prio 5 erstellen**

```bash
gh issue create \
  --title "🎲 Security: Gamified CAPTCHA (Rotations-Rätsel) — Prio 5" \
  --body "## Kontext
Sub-Issue von #215. Implementierung von Prio 5 nach Abschluss von Prio 1–4.

## Beschreibung
Interaktives Rotations-CAPTCHA als Bot-Erkennung:
- **Variante A:** Bild via Slider auf gleiche Rotation drehen wie Referenzbild (±15° Toleranz)
- **Variante B:** Semantisches Mini-Rätsel
- Serverseitig: korrekter Winkel als signiertes Token, Validierung bei Submit
- Integration in Confidence Score System (hoher Score → CAPTCHA erzwingen)

## Voraussetzungen
- Prio 1–4 implementiert (dieses Issue)
- Confidence Score System aktiv

## Aufwand
Groß (UI-Komponente + Server-Validierung + Score-Integration)" \
  --label "enhancement,security"
```

- [ ] **Step 2: Issue #215 kommentieren und schließen**

```bash
gh issue comment 215 --body "## Prio 1–4 implementiert ✅

- **Prio 1:** \`PendingRegistration\`-Flow — User wird erst nach E-Mail-Bestätigung angelegt
- **Prio 2:** JS-Timing-Challenge — Score +50 bei < 2s Submit-Zeit
- **Prio 3:** Tor/VPN-Detection — \`TorDetectionService\` + \`security:sync-tor-nodes\` (6h Scheduler)
- **Prio 4:** Disposable-Email-Blocklist — \`propaganistas/laravel-disposable-email\`

Confidence Score System als Fundament für zukünftige Signale (WebRTC, JA3, Canvas-Fingerprint) implementiert.

Prio 5 → separates Issue erstellt."

gh issue close 215
```

---

## Gesamttest-Run

Nach allen Tasks:

```bash
./vendor/bin/pest --parallel
```

Expected: Alle Tests grün. Keine Regression in bestehenden Tests.
