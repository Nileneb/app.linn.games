<?php

use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schedule;
use Spatie\Permission\Models\Role;

Artisan::addCommands([
    \App\Console\Commands\BackupExport::class,
    \App\Console\Commands\BackupImport::class,
    \App\Console\Commands\CleanupExpiredInvitations::class,
    \App\Console\Commands\Langdock\ApplyInstructionPatchFromExport::class,
    \App\Console\Commands\Langdock\GenerateFleetMapFromExport::class,
]);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Geplante Tasks ────────────────────────────────────────────────

Schedule::command('invitations:cleanup')->daily();

// ── Deploy Sub-Commands ──────────────────────────────────────────

Artisan::command('deploy:ensure-admin', function () {
    $email = 'bene@linn.games';

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::firstOrCreate(
        ['email' => $email],
        [
            'name' => 'Bene',
            'password' => bcrypt(bin2hex(random_bytes(32))),
            'status' => 'active',
        ],
    );

    if ($user->wasRecentlyCreated) {
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->info("Admin-User {$email} angelegt.");
    } else {
        $this->info("Admin-User {$email} existiert bereits.");
    }

    if (! $user->hasRole(\App\Enums\UserRole::ADMIN)) {
        $user->assignRole(\App\Enums\UserRole::ADMIN);
        $this->info('Admin-Rolle zugewiesen.');
    }
})->purpose('Ensure admin user and role exist');

Artisan::command('deploy:ensure-workspace', function () {
    $email = 'bene@linn.games';
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->warn("User {$email} nicht gefunden. Zuerst deploy:ensure-admin ausführen.");

        return 1;
    }

    $workspace = $user->ensureDefaultWorkspace();
    $starterCents = (int) config('services.credits.starter_amount_cents', 100);

    if ($workspace->credits_balance_cents <= 0 && $starterCents > 0) {
        app(CreditService::class)->topUp($workspace, $starterCents, 'Startguthaben nach Deploy');
        $euros = number_format($starterCents / 100, 2, ',', '.');
        $this->info("Startguthaben: {$euros} € für Workspace \"{$workspace->name}\" aufgeladen.");
    } else {
        $this->info("Workspace \"{$workspace->name}\" hat bereits {$workspace->credits_balance_cents} Cent Guthaben.");
    }
})->purpose('Ensure default workspace with starter credits exists');

Artisan::command('deploy:send-reset-link', function () {
    $email = 'bene@linn.games';
    $broker = Password::broker();
    $user = $broker->getUser(['email' => $email]);

    if (! $user) {
        $this->warn("User {$email} nicht gefunden für Password-Reset.");

        return 1;
    }

    // Throttle umgehen: alten Token löschen, neuen erstellen
    $broker->deleteToken($user);
    $token = $broker->createToken($user);
    $user->sendPasswordResetNotification($token);
    $this->info("Passwort-Reset-Link an {$email} gesendet.");
})->purpose('Send password reset link to admin user');

// ── Orchestrator ──────────────────────────────────────────────────

Artisan::command('deploy:post-deploy', function () {
    foreach (['deploy:ensure-admin', 'deploy:ensure-workspace', 'deploy:send-reset-link'] as $step) {
        $this->info("── {$step}");
        try {
            $this->call($step);
        } catch (\Throwable $e) {
            $this->error("{$step} fehlgeschlagen: {$e->getMessage()}");
        }
    }
})->purpose('Run all post-deploy steps (admin, workspace, password reset)');
