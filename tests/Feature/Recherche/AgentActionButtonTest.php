<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

function voltAgentButton(array $overrides = []): \Livewire\Features\SupportTesting\Testable
{
    $defaults = [
        'projekt' => test()->projekt,
        'agentConfigKey' => 'scoping_mapping_agent',
        'label' => '🎯 KI: Strukturierung starten',
        'phaseNr' => 1,
    ];

    return Volt::test('recherche.agent-action-button', array_merge($defaults, $overrides));
}

function createAgentResult(array $overrides = []): PhaseAgentResult
{
    $defaults = [
        'projekt_id' => test()->projekt->id,
        'user_id' => test()->user->id,
        'phase_nr' => 1,
        'agent_config_key' => 'scoping_mapping_agent',
        'status' => 'completed',
    ];

    return PhaseAgentResult::create(array_merge($defaults, $overrides));
}

beforeEach(function () {
    Config::set('services.langdock.api_key', 'test-api-key');
    Config::set('services.langdock.scoping_mapping_agent', 'scoping-uuid');
    Config::set('services.langdock.search_agent', 'search-uuid');
    Config::set('services.langdock.review_agent', 'review-uuid');

    $this->user = User::factory()->withoutTwoFactor()->create();
    $this->projekt = Projekt::factory()->create(['user_id' => $this->user->id]);
    $this->projekt->workspace()->update(['credits_balance_cents' => 100_000]);
    $this->actingAs($this->user);
});

test('agent button renders with label', function () {
    voltAgentButton()
        ->assertSee('🎯 KI: Strukturierung starten')
        ->assertSet('loading', false)
        ->assertSet('showModal', false);
});

test('agent button calls langdock api and shows result', function () {
    Queue::fake();

    voltAgentButton()
        ->call('runAgent')
        ->assertSet('showModal', true)
        ->assertSet('loadingPhase', '1');

    Queue::assertPushed(ProcessPhaseAgentJob::class);

    $result = createAgentResult(['content' => 'Empfohlenes Strukturmodell: PICO']);

    expect($result->content)->toBe('Empfohlenes Strukturmodell: PICO')
        ->and($result->status)->toBe('completed');
});

test('agent button shows error on api failure', function () {
    Queue::fake();

    voltAgentButton()
        ->call('runAgent')
        ->assertSet('showModal', true)
        ->assertSet('loadingPhase', '1');

    $result = createAgentResult([
        'status' => 'failed',
        'error_message' => 'API Error: Invalid request',
    ]);

    expect($result->status)->toBe('failed')
        ->and($result->error_message)->not->toBeEmpty();
});

test('agent button dispatches event on accept', function () {
    Queue::fake();

    createAgentResult([
        'phase_nr' => 4,
        'agent_config_key' => 'search_agent',
        'content' => 'KI-Ergebnis zum Übernehmen',
    ]);

    voltAgentButton([
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
    voltAgentButton([
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
