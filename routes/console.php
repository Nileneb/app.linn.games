<?php

use App\Models\User;
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
    $user->ensureDefaultWorkspace();

    // Send password reset link
    $status = Password::sendResetLink(['email' => $email]);

    if ($status === Password::RESET_LINK_SENT) {
        $this->info("Passwort-Reset-Link an {$email} gesendet.");
    } else {
        $this->warn("Passwort-Reset-Link konnte nicht gesendet werden: {$status}");
    }
})->purpose('Ensure admin user exists and send password reset link after deploy');
