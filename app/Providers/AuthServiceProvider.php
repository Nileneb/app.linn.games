<?php

namespace App\Providers;

use App\Models\Recherche\Projekt;
use App\Models\Workspace;
use App\Policies\ProjektPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any authorization services.
     * Manuelle Registrierung nötig: Laravels Auto-Discovery würde App\Policies\Recherche\ProjektPolicy
     * erwarten, die Policy liegt aber in App\Policies\ProjektPolicy (bewusst flach gehalten).
     * Neue Policies entweder hier eintragen oder in App\Policies\ nach Laravel-Konvention benennen.
     */
    public function boot(): void
    {
        Gate::policy(Projekt::class, ProjektPolicy::class);
        Gate::policy(Workspace::class, WorkspacePolicy::class);
    }
}
