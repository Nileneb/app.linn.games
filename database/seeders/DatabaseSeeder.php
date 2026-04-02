<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $adminEmail = env('MAIL_FROM_ADDRESS', 'admin@example.com');

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name'               => 'Admin',
                'password'           => Str::random(32),
                'status'             => 'active',
                'email_verified_at'  => now(),
            ]
        );

        $admin->ensureDefaultWorkspace();
        $admin->syncRoles(['admin']);

        if ($admin->wasRecentlyCreated) {
            $this->command?->info("Admin erstellt: {$adminEmail}");

            try {
                Password::sendResetLink(['email' => $adminEmail]);
                $this->command?->info("Passwort-Reset-Link gesendet an: {$adminEmail}");
            } catch (\Throwable $e) {
                $this->command?->warn("Reset-Link konnte nicht gesendet werden: {$e->getMessage()}");
            }
        } else {
            $this->command?->info("Admin bereits vorhanden: {$adminEmail}");
        }

        if (app()->isLocal()) {
            $this->call(RechercheDemoSeeder::class);
        }
    }
}
