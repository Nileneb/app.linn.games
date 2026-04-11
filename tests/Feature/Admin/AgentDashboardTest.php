<?php

use App\Models\PhaseAgentResult;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

function makeAdminUser(): User
{
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles(['admin']);

    return $user;
}

test('LangdockAgentsPage lädt ohne Fehler', function () {
    $admin = makeAdminUser();

    $this->actingAs($admin)
        ->get('/admin/claude-agenten')
        ->assertOk();
});

test('LangdockAgentsPage zeigt recentRuns wenn vorhanden', function () {
    $admin = makeAdminUser();

    PhaseAgentResult::factory()->create([
        'status' => 'completed',
        'agent_config_key' => 'search_agent',
        'phase_nr' => 3,
    ]);

    $this->actingAs($admin)
        ->get('/admin/claude-agenten')
        ->assertOk()
        ->assertSee('search_agent');
});

test('LangdockAgentsPage zeigt Hinweis wenn keine Runs vorhanden', function () {
    $admin = makeAdminUser();

    $this->actingAs($admin)
        ->get('/admin/claude-agenten')
        ->assertOk()
        ->assertSee('Noch keine Agent-Runs vorhanden');
});

test('AppLogViewerPage lädt ohne Fehler', function () {
    $admin = makeAdminUser();

    $this->actingAs($admin)
        ->get('/admin/app-logs')
        ->assertOk();
});

test('Admin-Seiten sind für Nicht-Admins gesperrt', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->get('/admin/claude-agenten')
        ->assertRedirect();
});
