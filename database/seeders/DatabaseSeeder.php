<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // User::factory(10)->create();

        $admin = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $admin->ensureDefaultWorkspace();
        $admin->syncRoles(['admin']);

        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Standard User',
                'password' => 'password',
                'status' => 'trial',
                'email_verified_at' => now(),
            ]
        );

        $user->ensureDefaultWorkspace();
        $user->syncRoles(['user']);

        $this->call(RechercheDemoSeeder::class);
    }
}
