<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
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
    Queue::fake();

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
        ->assertSet('showModal', true)
        ->assertSet('loadingPhase', '1');

    // Verify job was dispatched
    Queue::assertPushed(ProcessPhaseAgentJob::class);

    // Simulate job completion by creating result
    $result = PhaseAgentResult::create([
        'projekt_id' => $projekt->id,
        'user_id' => $user->id,
        'phase_nr' => 1,
        'agent_config_key' => 'scoping_mapping_agent',
        'status' => 'completed',
        'content' => 'Empfohlenes Strukturmodell: PICO',
    ]);

    // Verify result exists and has correct content
    expect($result->content)->toBe('Empfohlenes Strukturmodell: PICO')
        ->and($result->status)->toBe('completed');
});

test('agent button shows error on api failure', function () {
    Queue::fake();

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
        ->assertSet('showModal', true)
        ->assertSet('loadingPhase', '1');

    // Simulate job failure
    $result = PhaseAgentResult::create([
        'projekt_id' => $projekt->id,
        'user_id' => $user->id,
        'phase_nr' => 1,
        'agent_config_key' => 'scoping_mapping_agent',
        'status' => 'failed',
        'error_message' => 'API Error: Invalid request',
    ]);

    // Verify error result
    expect($result->status)->toBe('failed')
        ->and($result->error_message)->not->toBeEmpty();
});

test('agent button dispatches event on accept', function () {
    Queue::fake();

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);
    giveWorkspaceCredits($projekt);

    $this->actingAs($user);

    // Create completed result
    PhaseAgentResult::create([
        'projekt_id' => $projekt->id,
        'user_id' => $user->id,
        'phase_nr' => 4,
        'agent_config_key' => 'search_agent',
        'status' => 'completed',
        'content' => 'KI-Ergebnis zum Übernehmen',
    ]);

    Volt::test('recherche.agent-action-button', [
        'projekt' => $projekt,
        'agentConfigKey' => 'search_agent',
        'label' => '🔍 KI: Suchstrings generieren',
        'phaseNr' => 4,
    ])
        ->call('pollForResult')
        ->call('acceptResult')
        ->assertDispatched('agent-result-accepted')
        ->assertSet('showModal', false)
        ->assertSet('loadingPhase', null);
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
