<?php

use App\Models\User;
use App\Models\Webhook;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withoutTwoFactor()->create();
    $this->actingAs($this->user);
});

// ── Dashboard Chat: Speichern ────────────────────────────────────────

test('saveDashboard erstellt neuen webhook', function () {
    Volt::test('settings.webhooks')
        ->set('dashboardUrl', 'https://example.com/hook')
        ->set('dashboardSecret', 'geheim123')
        ->call('saveDashboard')
        ->assertHasNoErrors()
        ->assertSet('dashboardSaved', true)
        ->assertSet('dashboardHasSecret', true)
        ->assertDispatched('dashboard-saved');

    $webhook = Webhook::forUser($this->user->id, 'dashboard_chat');
    expect($webhook)->not->toBeNull();
    expect($webhook->url)->toBe('https://example.com/hook');
    expect($webhook->secret)->toBe('geheim123');
});

test('saveDashboard aktualisiert bestehenden webhook', function () {
    Webhook::create([
        'user_id'         => $this->user->id,
        'frontend_object' => 'dashboard_chat',
        'name'            => 'Dashboard Chat',
        'slug'            => 'dashboard-chat-old',
        'url'             => 'https://old.example.com/hook',
        'secret'          => 'old-secret',
    ]);

    Volt::test('settings.webhooks')
        ->set('dashboardUrl', 'https://new.example.com/hook')
        ->set('dashboardSecret', 'new-secret')
        ->call('saveDashboard')
        ->assertHasNoErrors()
        ->assertSet('dashboardSaved', true);

    expect(Webhook::where('user_id', $this->user->id)->where('frontend_object', 'dashboard_chat')->count())->toBe(1);

    $webhook = Webhook::forUser($this->user->id, 'dashboard_chat');
    expect($webhook->url)->toBe('https://new.example.com/hook');
    expect($webhook->secret)->toBe('new-secret');
});

test('saveDashboard ueberschreibt secret nicht bei SECRET_MASK', function () {
    Webhook::create([
        'user_id'         => $this->user->id,
        'frontend_object' => 'dashboard_chat',
        'name'            => 'Dashboard Chat',
        'slug'            => 'dashboard-chat-mask',
        'url'             => 'https://example.com/hook',
        'secret'          => 'original-secret',
    ]);

    Volt::test('settings.webhooks')
        ->set('dashboardUrl', 'https://updated-url.example.com/hook')
        ->set('dashboardSecret', '********')
        ->call('saveDashboard')
        ->assertHasNoErrors();

    $webhook = Webhook::forUser($this->user->id, 'dashboard_chat');
    expect($webhook->url)->toBe('https://updated-url.example.com/hook');
    expect($webhook->secret)->toBe('original-secret');
});

test('saveDashboard validiert URL als required', function () {
    Volt::test('settings.webhooks')
        ->set('dashboardUrl', '')
        ->call('saveDashboard')
        ->assertHasErrors(['dashboardUrl' => 'required']);
});

test('saveDashboard validiert URL-Format', function () {
    Volt::test('settings.webhooks')
        ->set('dashboardUrl', 'kein-url-format')
        ->call('saveDashboard')
        ->assertHasErrors(['dashboardUrl' => 'url']);
});

test('saveDashboard validiert URL max 500 Zeichen', function () {
    Volt::test('settings.webhooks')
        ->set('dashboardUrl', 'https://example.com/' . str_repeat('a', 500))
        ->call('saveDashboard')
        ->assertHasErrors(['dashboardUrl' => 'max']);
});

// ── Dashboard Chat: Entfernen ────────────────────────────────────────

test('clearDashboard loescht webhook und setzt state zurueck', function () {
    Webhook::create([
        'user_id'         => $this->user->id,
        'frontend_object' => 'dashboard_chat',
        'name'            => 'Dashboard Chat',
        'slug'            => 'dashboard-chat-del',
        'url'             => 'https://example.com/hook',
        'secret'          => null,
    ]);

    Volt::test('settings.webhooks')
        ->call('clearDashboard')
        ->assertSet('dashboardUrl', '')
        ->assertSet('dashboardSecret', '')
        ->assertSet('dashboardHasSecret', false)
        ->assertSet('dashboardSaved', false);

    expect(Webhook::forUser($this->user->id, 'dashboard_chat'))->toBeNull();
});

// ── Recherche starten: Speichern ─────────────────────────────────────

test('saveRecherche erstellt neuen webhook', function () {
    Volt::test('settings.webhooks')
        ->set('rechercheUrl', 'https://example.com/recherche-hook')
        ->set('rechercheSecret', 'rech-secret')
        ->call('saveRecherche')
        ->assertHasNoErrors()
        ->assertSet('rechercheSaved', true)
        ->assertSet('rechercheHasSecret', true)
        ->assertDispatched('recherche-saved');

    $webhook = Webhook::forUser($this->user->id, 'recherche_start');
    expect($webhook)->not->toBeNull();
    expect($webhook->url)->toBe('https://example.com/recherche-hook');
    expect($webhook->secret)->toBe('rech-secret');
});

test('saveRecherche aktualisiert bestehenden webhook', function () {
    Webhook::create([
        'user_id'         => $this->user->id,
        'frontend_object' => 'recherche_start',
        'name'            => 'Recherche starten',
        'slug'            => 'recherche-start-old',
        'url'             => 'https://old.example.com/rech',
        'secret'          => 'old-rech',
    ]);

    Volt::test('settings.webhooks')
        ->set('rechercheUrl', 'https://new.example.com/rech')
        ->set('rechercheSecret', 'new-rech')
        ->call('saveRecherche')
        ->assertHasNoErrors();

    expect(Webhook::where('user_id', $this->user->id)->where('frontend_object', 'recherche_start')->count())->toBe(1);

    $webhook = Webhook::forUser($this->user->id, 'recherche_start');
    expect($webhook->url)->toBe('https://new.example.com/rech');
    expect($webhook->secret)->toBe('new-rech');
});

test('saveRecherche ueberschreibt secret nicht bei SECRET_MASK', function () {
    Webhook::create([
        'user_id'         => $this->user->id,
        'frontend_object' => 'recherche_start',
        'name'            => 'Recherche starten',
        'slug'            => 'recherche-start-mask',
        'url'             => 'https://example.com/rech',
        'secret'          => 'original-rech-secret',
    ]);

    Volt::test('settings.webhooks')
        ->set('rechercheUrl', 'https://updated.example.com/rech')
        ->set('rechercheSecret', '********')
        ->call('saveRecherche')
        ->assertHasNoErrors();

    $webhook = Webhook::forUser($this->user->id, 'recherche_start');
    expect($webhook->url)->toBe('https://updated.example.com/rech');
    expect($webhook->secret)->toBe('original-rech-secret');
});

test('saveRecherche validiert URL als required', function () {
    Volt::test('settings.webhooks')
        ->set('rechercheUrl', '')
        ->call('saveRecherche')
        ->assertHasErrors(['rechercheUrl' => 'required']);
});

// ── Recherche starten: Entfernen ─────────────────────────────────────

test('clearRecherche loescht webhook und setzt state zurueck', function () {
    Webhook::create([
        'user_id'         => $this->user->id,
        'frontend_object' => 'recherche_start',
        'name'            => 'Recherche starten',
        'slug'            => 'recherche-start-del',
        'url'             => 'https://example.com/rech',
        'secret'          => null,
    ]);

    Volt::test('settings.webhooks')
        ->call('clearRecherche')
        ->assertSet('rechercheUrl', '')
        ->assertSet('rechercheSecret', '')
        ->assertSet('rechercheHasSecret', false)
        ->assertSet('rechercheSaved', false);

    expect(Webhook::forUser($this->user->id, 'recherche_start'))->toBeNull();
});

// ── Mount: bestehende Webhooks laden ─────────────────────────────────

test('mount laedt bestehende webhooks mit maskiertem secret', function () {
    Webhook::create([
        'user_id'         => $this->user->id,
        'frontend_object' => 'dashboard_chat',
        'name'            => 'Dashboard Chat',
        'slug'            => 'dashboard-chat-mount',
        'url'             => 'https://example.com/dash',
        'secret'          => 'dash-secret',
    ]);

    Webhook::create([
        'user_id'         => $this->user->id,
        'frontend_object' => 'recherche_start',
        'name'            => 'Recherche starten',
        'slug'            => 'recherche-start-mount',
        'url'             => 'https://example.com/rech',
        'secret'          => null,
    ]);

    Volt::test('settings.webhooks')
        ->assertSet('dashboardUrl', 'https://example.com/dash')
        ->assertSet('dashboardHasSecret', true)
        ->assertSet('dashboardSecret', '********')
        ->assertSet('rechercheUrl', 'https://example.com/rech')
        ->assertSet('rechercheHasSecret', false)
        ->assertSet('rechercheSecret', '');
});

// ── Isolation: kein Zugriff auf fremde Webhooks ──────────────────────

test('clearDashboard loescht nur eigene webhooks', function () {
    $other = User::factory()->withoutTwoFactor()->create();

    Webhook::create([
        'user_id'         => $other->id,
        'frontend_object' => 'dashboard_chat',
        'name'            => 'Dashboard Chat',
        'slug'            => 'dashboard-chat-other',
        'url'             => 'https://other.example.com/hook',
        'secret'          => null,
    ]);

    Volt::test('settings.webhooks')
        ->call('clearDashboard');

    expect(Webhook::forUser($other->id, 'dashboard_chat'))->not->toBeNull();
});
