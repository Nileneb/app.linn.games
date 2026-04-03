<?php

use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('deploy:post-deploy', function () {
    $email = 'bene@linn.games';

    // Ensure admin role exists
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    // Ensure admin user exists (idempotent)
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

    // Ensure admin role
    if (! $user->hasRole('admin')) {
        $user->assignRole('admin');
        $this->info('Admin-Rolle zugewiesen.');
    }

    // Ensure default workspace
    $workspace = $user->ensureDefaultWorkspace();

    if ($workspace->credits_balance_cents <= 0) {
        app(CreditService::class)->topUp($workspace, 100, 'Startguthaben nach Deploy');
        $this->info("Startguthaben: 1,00 \u20ac f\u00fcr Workspace \"{$workspace->name}\" aufgeladen.");
    } else {
        $this->info("Workspace \"{$workspace->name}\" hat bereits {$workspace->credits_balance_cents} Cent Guthaben.");
    }

    // Send password reset link
    $status = Password::sendResetLink(['email' => $email]);

    if ($status === Password::RESET_LINK_SENT) {
        $this->info("Passwort-Reset-Link an {$email} gesendet.");
    } else {
        $this->warn("Passwort-Reset-Link konnte nicht gesendet werden: {$status}");
    }
})->purpose('Ensure admin user exists and send password reset link after deploy');
