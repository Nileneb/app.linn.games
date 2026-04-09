<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DevSeeder extends Seeder
{
    /**
     * Seed development/test users with verified emails and no 2FA.
     * Password for all dev users: "password"
     */
    public function run(): void
    {
        // Ensure roles exist
        foreach (UserRole::all() as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // 1. Editor User
        $editor = User::updateOrCreate(
            ['email' => 'editor@test.local'],
            [
                'name' => 'Editor Test User',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]
        );
        $editor->syncRoles([UserRole::EDITOR]);
        $workspace = $editor->ensureDefaultWorkspace();

        // Add starter credits if new
        if ($editor->wasRecentlyCreated && $workspace->credits_balance_cents <= 0) {
            $starterCents = (int) config('services.credits.starter_amount_cents', 100);
            app(\App\Services\CreditService::class)->topUp($workspace, $starterCents, 'Startguthaben (Dev)');
        }

        $this->command?->info('✓ Editor User: editor@test.local / password');

        // 2. Member User
        $member = User::updateOrCreate(
            ['email' => 'member@test.local'],
            [
                'name' => 'Member Test User',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]
        );
        $member->syncRoles([UserRole::MITGLIED]);
        $workspace = $member->ensureDefaultWorkspace();

        // Add starter credits if new
        if ($member->wasRecentlyCreated && $workspace->credits_balance_cents <= 0) {
            $starterCents = (int) config('services.credits.starter_amount_cents', 100);
            app(\App\Services\CreditService::class)->topUp($workspace, $starterCents, 'Startguthaben (Dev)');
        }

        $this->command?->info('✓ Member User: member@test.local / password');

        // 3. Admin User (no Workspace, just testing Admin panel)
        $admin = User::updateOrCreate(
            ['email' => 'admin-test@test.local'],
            [
                'name' => 'Admin Test User',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]
        );
        $admin->syncRoles([UserRole::ADMIN]);
        $admin->ensureDefaultWorkspace();

        $this->command?->info('');
        $this->command?->info('🎯 Dev Users Created:');
        $this->command?->info('   - editor@test.local (Editor role) — Recherche Features');
        $this->command?->info('   - member@test.local (Member role) — Standard Member');
        $this->command?->info('   - admin-test@test.local (Admin role) — Admin Panel');
        $this->command?->info('');
        $this->command?->info('🔑 Password for all: password');
        $this->command?->info('');
    }
}
