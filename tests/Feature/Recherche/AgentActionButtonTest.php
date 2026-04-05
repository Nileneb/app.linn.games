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
        'projekt'        => test()->projekt,
        'agentConfigKey' => 'scoping_mapping_agent',
        'label'          => 'KI starten',
        'phaseNr'        => 1,
    ];

    return Volt::test('recherche.agent-action-button', array_merge($defaults, $overrides));
}

beforeEach(function () {
    Config::set('services.langdock.api_key', 'test-api-key');
    Config::set('services.langdock.scoping_mapping_agent', 'scoping-uuid');

    $this->user    = User::factory()->withoutTwoFactor()->create();
    $this->projekt = Projekt::factory()->create(['user_id' => $this->user->id]);
    $this->projekt->workspace()->update(['credits_balance_cents' => 100_000]);
    $this->actingAs($this->user);
});

test('agent button renders with label', function () {
    voltAgentButton()
        ->assertSee('KI starten')
        ->assertSet('dispatched', false);
});

test('agent button dispatches job and sets dispatched flag', function () {
    Queue::fake();

    voltAgentButton()
        ->call('runAgent')
        ->assertSet('dispatched', true);

    Queue::assertPushed(ProcessPhaseAgentJob::class);
});

test('agent button shows spinner after dispatch', function () {
    Queue::fake();

    voltAgentButton()
        ->call('runAgent')
        ->assertSee('KI läuft im Hintergrund');
});
