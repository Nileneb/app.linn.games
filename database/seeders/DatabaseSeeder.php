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

        $workspace = $admin->ensureDefaultWorkspace();
        $admin->syncRoles(['admin']);

        if ($admin->wasRecentlyCreated && $workspace->credits_balance_cents <= 0) {
            $starterCents = (int) config('services.credits.starter_amount_cents', 100);
            app(\App\Services\CreditService::class)->topUp($workspace, $starterCents, 'Startguthaben');
        }

        if ($admin->wasRecentlyCreated) {
            $this->command?->info("Admin erstellt: {$adminEmail}");

            try {
                $token = Password::createToken($admin);
                $resetUrl = url(route('password.reset', [
                    'token' => $token,
                    'email' => $adminEmail,
                ], false));
                $this->command?->info("Passwort-Reset-URL (einmalig): {$resetUrl}");
            } catch (\Throwable $e) {
                $this->command?->warn("Reset-Token konnte nicht erstellt werden: {$e->getMessage()}");
            }
        } else {
            $this->command?->info("Admin bereits vorhanden: {$adminEmail}");
        }

        if (app()->isLocal()) {
            $this->call(DevSeeder::class);
            $this->call(RechercheDemoSeeder::class);
        }
    }
}
