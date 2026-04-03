<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

// Helper to give the projekt's workspace enough credits for tests
function giveWorkspaceCredits(Projekt $projekt, int $cents = 100_000): void
{
    $projekt->workspace()->update(['credits_balance_cents' => $cents]);
}

beforeEach(function () {
    Config::set('services.langdock.api_key', 'test-api-key');
    Config::set('services.langdock.scoping_mapping_agent', 'scoping-uuid');
    Config::set('services.langdock.search_agent', 'search-uuid');
    Config::set('services.langdock.review_agent', 'review-uuid');
});

test('agent button renders with label', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Volt::test('recherche.agent-action-button', [
        'projekt' => $projekt,
        'agentConfigKey' => 'scoping_mapping_agent',
        'label' => '🎯 KI: Strukturierung starten',
        'phaseNr' => 1,
    ])
        ->assertSee('🎯 KI: Strukturierung starten')
        ->assertSet('loading', false)
        ->assertSet('showModal', false);
});

test('agent button calls langdock api and shows result', function () {
    Http::fake([
        '*' => Http::response([
            'messages' => [['id' => 'r-1', 'role' => 'assistant', 'content' => 'Empfohlenes Strukturmodell: PICO']],
        ], 200),
    ]);

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create([
        'user_id' => $user->id,
        'forschungsfrage' => 'Wie wirkt sich X auf Y aus?',
    ]);
    giveWorkspaceCredits($projekt);

    $this->actingAs($user);

    Volt::test('recherche.agent-action-button', [
        'projekt' => $projekt,
        'agentConfigKey' => 'scoping_mapping_agent',
        'label' => '🎯 KI: Strukturierung starten',
        'phaseNr' => 1,
    ])
        ->call('runAgent')
        ->assertSet('loading', false)
        ->assertSet('showModal', true)
        ->assertSet('result', 'Empfohlenes Strukturmodell: PICO')
        ->assertSet('error', '');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.langdock.com')
            && $request->hasHeader('Authorization', 'Bearer test-api-key');
    });
});

test('agent button shows error on api failure', function () {
    Http::fake([
        '*' => Http::response('Internal Server Error', 500),
    ]);

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    giveWorkspaceCredits($projekt);

    $this->actingAs($user);

    Volt::test('recherche.agent-action-button', [
        'projekt' => $projekt,
        'agentConfigKey' => 'scoping_mapping_agent',
        'label' => '🎯 KI: Strukturierung starten',
        'phaseNr' => 1,
    ])
        ->call('runAgent')
        ->assertSet('loading', false)
        ->assertSet('showModal', true)
        ->assertSet('result', '')
        ->assertNotSet('error', '');
});

test('agent button dispatches event on accept', function () {
    Http::fake([
        '*' => Http::response([
            'messages' => [['id' => 'r-2', 'role' => 'assistant', 'content' => 'KI-Ergebnis zum Übernehmen']],
        ], 200),
    ]);

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    giveWorkspaceCredits($projekt);

    $this->actingAs($user);

    Volt::test('recherche.agent-action-button', [
        'projekt' => $projekt,
        'agentConfigKey' => 'search_agent',
        'label' => '🔍 KI: Suchstrings generieren',
        'phaseNr' => 4,
    ])
        ->call('runAgent')
        ->call('acceptResult')
        ->assertDispatched('agent-result-accepted')
        ->assertSet('showModal', false)
        ->assertSet('result', '');
});

test('agent button dismiss closes modal', function () {
    Http::fake([
        '*' => Http::response([
            'messages' => [['id' => 'r-3', 'role' => 'assistant', 'content' => 'Ergebnis']],
        ], 200),
    ]);

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    giveWorkspaceCredits($projekt);

    $this->actingAs($user);

    Volt::test('recherche.agent-action-button', [
        'projekt' => $projekt,
        'agentConfigKey' => 'review_agent',
        'label' => '🧹 KI: Screening durchführen',
        'phaseNr' => 5,
    ])
        ->call('runAgent')
        ->assertSet('showModal', true)
        ->call('dismissResult')
        ->assertSet('showModal', false)
        ->assertSet('result', '')
        ->assertSet('error', '');
});
