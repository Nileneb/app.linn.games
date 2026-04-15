<?php

use App\Services\ClaudeCliService;
use Illuminate\Support\Facades\Process;

uses(Tests\TestCase::class);

test('call() gibt content aus Claude CLI JSON-Output zurück', function () {
    // Force CLI mode — CLAUDE_USE_DIRECT_API=true in .env would bypass Process::fake()
    config(['services.anthropic.use_direct_api' => false]);

    Process::fake([
        '*claude*' => Process::result(
            output: json_encode([
                'type' => 'result',
                'subtype' => 'success',
                'is_error' => false,
                'result' => 'Hallo vom Mock-Claude',
            ]),
            exitCode: 0,
        ),
    ]);

    $service = app(ClaudeCliService::class);
    $result = $service->call('Test-Frage', ['projekt_id' => 'abc123']);

    expect($result['content'])->toBe('Hallo vom Mock-Claude');
});

test('call() wirft ClaudeCliException bei Fehler-Exit-Code', function () {
    config(['services.anthropic.use_direct_api' => false]);

    Process::fake([
        '*claude*' => Process::result(
            output: '',
            errorOutput: 'Claude CLI not found',
            exitCode: 1,
        ),
    ]);

    $service = app(ClaudeCliService::class);

    expect(fn () => $service->call('Test', []))
        ->toThrow(\App\Services\ClaudeCliException::class);
});

test('call() sendet --output-format json und --print Flags', function () {
    config(['services.anthropic.use_direct_api' => false]);

    Process::fake([
        '*claude*' => Process::result(
            output: json_encode(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'result' => 'OK']),
            exitCode: 0,
        ),
    ]);

    $service = app(ClaudeCliService::class);
    $service->call('Frage', ['projekt_id' => 'xyz']);

    Process::assertRan(fn ($process) => str_contains($process->command, '--output-format') &&
        str_contains($process->command, 'json') &&
        str_contains($process->command, '--print')
    );
});

test('callForPhase() verwendet --model Flag und gibt Token-Info zurück', function () {
    config(['services.anthropic.use_direct_api' => false]);
    config(['services.anthropic.use_ollama_workers' => false]);
    config(['services.anthropic.agent_models.search_agent' => 'claude-haiku-4-5-20251001']);
    config(['services.anthropic.agents.search_agent' => 'pico-agent']);
    config(['services.anthropic.api_key' => 'test-key']);

    Process::fake([
        '*claude*' => Process::result(
            output: json_encode([
                'result' => 'Search result',
                'is_error' => false,
                'total_cost_usd' => 0.002,
                'usage' => ['input_tokens' => 500, 'output_tokens' => 200],
            ]),
            exitCode: 0,
        ),
    ]);

    $service = app(ClaudeCliService::class);
    $result = $service->callForPhase(
        'search_agent',
        [['role' => 'user', 'content' => 'Suche starten']],
    );

    expect($result['content'])->toBe('Search result');
    expect($result['cost_usd'])->toBe(0.002);
    expect($result['input_tokens'])->toBe(500);
    expect($result['output_tokens'])->toBe(200);

    Process::assertRan(fn ($process) => str_contains($process->command, '--model') &&
        str_contains($process->command, 'claude-haiku')
    );
});

test('callForPhase() wirft Exception bei CLI-Fehler', function () {
    config(['services.anthropic.use_direct_api' => false]);
    config(['services.anthropic.use_ollama_workers' => false]);
    config(['services.anthropic.agents.search_agent' => 'pico-agent']);
    config(['services.anthropic.api_key' => 'test-key']);

    Process::fake([
        '*claude*' => Process::result(output: '', errorOutput: 'Error', exitCode: 1),
    ]);

    $service = app(ClaudeCliService::class);

    expect(fn () => $service->callForPhase('search_agent', [['role' => 'user', 'content' => 'test']]))
        ->toThrow(\App\Services\ClaudeCliException::class);
});
