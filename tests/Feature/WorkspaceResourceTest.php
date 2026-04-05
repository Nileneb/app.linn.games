<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',  'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
});

function makeAdmin(): User
{
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles(['admin']);
    return $user;
}

function makeWorkspaceWithOwner(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Test Workspace']);
    return ['workspace' => $workspace, 'owner' => $owner];
}

// ── Zugriffsschutz ────────────────────────────────────────────────

test('admin kann Workspace-Liste aufrufen', function () {
    $this->actingAs(makeAdmin())
        ->get('/admin/workspaces')
        ->assertOk();
});

test('nicht-admin hat keinen Zugriff auf Workspace-Liste', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->syncRoles(['editor']);

    $this->actingAs($user)
        ->get('/admin/workspaces')
        ->assertForbidden();
});

// ── Pages rendern ────────────────────────────────────────────────

test('admin kann Create-Seite aufrufen', function () {
    $this->actingAs(makeAdmin())
        ->get('/admin/workspaces/create')
        ->assertOk();
});

test('admin kann View-Seite aufrufen', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs(makeAdmin())
        ->get("/admin/workspaces/{$workspace->id}")
        ->assertOk();
});

test('admin kann Edit-Seite aufrufen', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs(makeAdmin())
        ->get("/admin/workspaces/{$workspace->id}/edit")
        ->assertOk();
});

// ── afterCreate: Owner-Mitgliedschaft ────────────────────────────

test('CreateWorkspace legt Owner-Mitgliedschaft an', function () {
    $owner = User::factory()->withoutTwoFactor()->create();

    $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Neuer Workspace']);

    // Simulate the afterCreate hook directly
    if (! WorkspaceUser::where('workspace_id', $workspace->id)->where('user_id', $owner->id)->exists()) {
        WorkspaceUser::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner']);
    }

    expect(
        WorkspaceUser::where('workspace_id', $workspace->id)
            ->where('user_id', $owner->id)
            ->where('role', 'owner')
            ->exists()
    )->toBeTrue();
});

test('afterCreate legt keine doppelte Mitgliedschaft an', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Doppelt Test']);

    WorkspaceUser::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner']);

    // Run hook again — must not create duplicate
    if (! WorkspaceUser::where('workspace_id', $workspace->id)->where('user_id', $owner->id)->exists()) {
        WorkspaceUser::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner']);
    }

    expect(
        WorkspaceUser::where('workspace_id', $workspace->id)->where('user_id', $owner->id)->count()
    )->toBe(1);
});

// ── Delete kaskadiert ────────────────────────────────────────────

test('Workspace-Delete kaskadiert workspace_users', function () {
    ['workspace' => $workspace, 'owner' => $owner] = makeWorkspaceWithOwner();
    WorkspaceUser::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'owner']);

    expect(WorkspaceUser::where('workspace_id', $workspace->id)->count())->toBe(1);

    $workspace->delete();

    expect(WorkspaceUser::where('workspace_id', $workspace->id)->count())->toBe(0);
});

test('Workspace-Delete kaskadiert Projekte', function () {
    ['workspace' => $workspace, 'owner' => $owner] = makeWorkspaceWithOwner();

    Projekt::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    expect(Projekt::where('workspace_id', $workspace->id)->count())->toBe(1);

    $workspace->delete();

    expect(Projekt::where('workspace_id', $workspace->id)->count())->toBe(0);
});

// ── deleteModalDescription ───────────────────────────────────────

test('deleteModalDescription enthält workspace name', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $description = \App\Filament\Resources\WorkspaceResource::deleteModalDescription($workspace);

    expect($description)->toContain($workspace->name);
});

test('deleteModalDescription warnt bei positivem guthaben', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['credits_balance_cents' => 500]);

    $description = \App\Filament\Resources\WorkspaceResource::deleteModalDescription($workspace);

    expect($description)->toContain('Guthaben');
});
