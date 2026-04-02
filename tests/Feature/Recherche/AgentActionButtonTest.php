<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

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
        'app.langdock.com/*' => Http::response([
            'content' => 'Empfohlenes Strukturmodell: PICO',
        ], 200),
    ]);

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create([
        'user_id' => $user->id,
        'forschungsfrage' => 'Wie wirkt sich X auf Y aus?',
    ]);

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
        return str_contains($request->url(), 'scoping-uuid/completions')
            && str_contains($request['messages'][0]['content'], 'Wie wirkt sich X auf Y aus?');
    });
});

test('agent button shows error on api failure', function () {
    Http::fake([
        'app.langdock.com/*' => Http::response('Internal Server Error', 500),
    ]);

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

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
        'app.langdock.com/*' => Http::response([
            'content' => 'KI-Ergebnis zum Übernehmen',
        ], 200),
    ]);

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

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
        'app.langdock.com/*' => Http::response([
            'content' => 'Ergebnis',
        ], 200),
    ]);

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

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
