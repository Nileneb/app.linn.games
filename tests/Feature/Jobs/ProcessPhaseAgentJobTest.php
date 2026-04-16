<?php

use App\Jobs\ProcessPhaseAgentJob;
use App\Models\PhaseAgentResult;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ArtifactService;
use App\Services\PromptLoaderService;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'credits_balance_cents' => 100000,
    ]);
    $this->projekt = Projekt::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);

    config(['services.anthropic.api_key' => 'test-key']);
    config(['services.anthropic.agents.agent_id' => 'chat-agent']);
    config(['services.anthropic.agents.scoping_mapping_agent' => 'mapping-agent']);
    config(['services.anthropic.agents.search_agent' => 'pico-agent']);
    // Force CLI subprocess path so Process::fake() works regardless of .env values
    config(['services.anthropic.use_direct_api' => false]);
    config(['services.anthropic.use_ollama_workers' => false]);

    // Mock PromptLoaderService to avoid filesystem reads in tests
    $this->mock(PromptLoaderService::class, function ($mock) {
        $mock->shouldReceive('buildSystemPrompt')->andReturn('Test system prompt');
    });
});

test('ProcessPhaseAgentJob dispatches successfully', function () {
    Queue::fake();

    $messages = [['role' => 'user', 'content' => 'Test question']];
    $context = ['projekt_id' => $this->projekt->id, 'phase_nr' => 1];

    ProcessPhaseAgentJob::dispatch($this->projekt->id, 1, 'test_agent', $messages, $context);

    Queue::assertPushed(ProcessPhaseAgentJob::class);
});

test('ProcessPhaseAgentJob creates pending result record and completes', function () {
    Process::fake([
        '*claude*' => Process::result(
            output: json_encode([
                'result' => 'Test response',
                'is_error' => false,
                'total_cost_usd' => 0.001,
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ]),
            exitCode: 0,
        ),
    ]);

    $this->mock(ArtifactService::class, function ($mock) {
        $mock->shouldReceive('persistFromAgentResponse')
            ->once()
            ->andReturn(['display_content' => 'Test response', 'stored_paths' => []]);
    });

    $job = new ProcessPhaseAgentJob(
        $this->projekt->id,
        1,
        'agent_id',
        [['role' => 'user', 'content' => 'Test']],
        ['projekt_id' => $this->projekt->id, 'user_id' => $this->user->id, 'workspace_id' => $this->workspace->id]
    );

    $job->handle();

    $result = PhaseAgentResult::where('projekt_id', $this->projekt->id)
        ->where('phase_nr', 1)
        ->first();

    expect($result)->not->toBeNull()
        ->and($result->status)->toBe('completed');
});

test('ProcessPhaseAgentJob marks result as completed on success', function () {
    Process::fake([
        '*claude*' => Process::result(
            output: json_encode([
                'result' => 'Successful response',
                'is_error' => false,
                'total_cost_usd' => 0.002,
                'usage' => ['input_tokens' => 200, 'output_tokens' => 100],
            ]),
            exitCode: 0,
        ),
    ]);

    $this->mock(ArtifactService::class, function ($mock) {
        $mock->shouldReceive('persistFromAgentResponse')
            ->once()
            ->andReturn(['display_content' => 'Successful response', 'stored_paths' => []]);
    });

    $job = new ProcessPhaseAgentJob(
        $this->projekt->id,
        2,
        'scoping_mapping_agent',
        [['role' => 'user', 'content' => 'Test']],
        ['projekt_id' => $this->projekt->id, 'user_id' => $this->user->id, 'workspace_id' => $this->workspace->id]
    );

    $job->handle();

    $result = PhaseAgentResult::where('projekt_id', $this->projekt->id)
        ->where('phase_nr', 2)
        ->first();

    expect($result->status)->toBe('completed')
        ->and($result->error_message)->toBeNull();
});

test('ProcessPhaseAgentJob marks result as failed on CLI error', function () {
    Process::fake([
        '*claude*' => Process::result(output: '', errorOutput: 'CLI error', exitCode: 1),
    ]);

    $job = new ProcessPhaseAgentJob(
        $this->projekt->id,
        3,
        'search_agent',
        [['role' => 'user', 'content' => 'Test']],
        ['projekt_id' => $this->projekt->id, 'user_id' => $this->user->id, 'workspace_id' => $this->workspace->id]
    );

    $job->handle();

    $result = PhaseAgentResult::where('projekt_id', $this->projekt->id)
        ->where('phase_nr', 3)
        ->first();

    expect($result->status)->toBe('failed')
        ->and($result->error_message)->not->toBeEmpty();
});

test('PhaseAgentResult::latestPending returns only non-pending results', function () {
    PhaseAgentResult::create([
        'projekt_id' => $this->projekt->id,
        'user_id' => $this->user->id,
        'phase_nr' => 1,
        'agent_config_key' => 'test_agent',
        'status' => 'pending',
        'content' => null,
    ]);

    $result = PhaseAgentResult::latestPending($this->projekt->id, 1, 'test_agent');
    expect($result)->toBeNull();

    PhaseAgentResult::create([
        'projekt_id' => $this->projekt->id,
        'user_id' => $this->user->id,
        'phase_nr' => 1,
        'agent_config_key' => 'test_agent',
        'status' => 'completed',
        'content' => 'Result content',
    ]);

    $result = PhaseAgentResult::latestPending($this->projekt->id, 1, 'test_agent');
    expect($result)->not->toBeNull()
        ->and($result->status)->toBe('completed')
        ->and($result->content)->toBe('Result content');
});

test('PhaseAgentResult::latestPending returns latest result', function () {
    PhaseAgentResult::create([
        'projekt_id' => $this->projekt->id,
        'user_id' => $this->user->id,
        'phase_nr' => 1,
        'agent_config_key' => 'test_agent',
        'status' => 'completed',
        'content' => 'Old result',
        'created_at' => now()->subMinutes(5),
    ]);

    PhaseAgentResult::create([
        'projekt_id' => $this->projekt->id,
        'user_id' => $this->user->id,
        'phase_nr' => 1,
        'agent_config_key' => 'test_agent',
        'status' => 'completed',
        'content' => 'New result',
        'created_at' => now(),
    ]);

    $result = PhaseAgentResult::latestPending($this->projekt->id, 1, 'test_agent');
    expect($result->content)->toBe('New result');
});

test('PhaseAgentResult markCompleted updates status and content', function () {
    $result = PhaseAgentResult::create([
        'projekt_id' => $this->projekt->id,
        'user_id' => $this->user->id,
        'phase_nr' => 1,
        'agent_config_key' => 'test_agent',
        'status' => 'pending',
        'content' => null,
    ]);

    $result->markCompleted('Test response');

    expect($result->refresh()->status)->toBe('completed')
        ->and($result->content)->toBe('Test response')
        ->and($result->error_message)->toBeNull();
});

test('PhaseAgentResult markFailed updates status and error message', function () {
    $result = PhaseAgentResult::create([
        'projekt_id' => $this->projekt->id,
        'user_id' => $this->user->id,
        'phase_nr' => 1,
        'agent_config_key' => 'test_agent',
        'status' => 'pending',
        'content' => null,
    ]);

    $result->markFailed('Agent timeout');

    expect($result->refresh()->status)->toBe('failed')
        ->and($result->error_message)->toBe('Agent timeout')
        ->and($result->content)->toBeNull();
});

test('Projekt has phaseAgentResults relation', function () {
    PhaseAgentResult::create([
        'projekt_id' => $this->projekt->id,
        'user_id' => $this->user->id,
        'phase_nr' => 1,
        'agent_config_key' => 'test_agent',
        'status' => 'completed',
        'content' => 'Result',
    ]);

    PhaseAgentResult::create([
        'projekt_id' => $this->projekt->id,
        'user_id' => $this->user->id,
        'phase_nr' => 2,
        'agent_config_key' => 'test_agent',
        'status' => 'completed',
        'content' => 'Result 2',
    ]);

    expect($this->projekt->phaseAgentResults()->count())->toBe(2)
        ->and($this->projekt->phaseAgentResults()->pluck('phase_nr')->toArray())->toBe([1, 2]);
});

test('ProcessPhaseAgentJob handles cascade delete on projekt deletion', function () {
    PhaseAgentResult::create([
        'projekt_id' => $this->projekt->id,
        'user_id' => $this->user->id,
        'phase_nr' => 1,
        'agent_config_key' => 'test_agent',
        'status' => 'completed',
        'content' => 'Result',
    ]);

    expect(PhaseAgentResult::count())->toBe(1);

    $this->projekt->delete();

    expect(PhaseAgentResult::count())->toBe(0);
});
