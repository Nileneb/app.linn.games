<?php

use App\Actions\SendAgentMessage;
use App\Services\ClaudeAgentException;
use App\Services\ClaudeService;
use App\Services\InsufficientCreditsException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Config::set('services.anthropic.api_key', 'test-key');
    Config::set('services.anthropic.agents.search_agent', 'search-agent.md');
    Config::set('services.anthropic.model', 'claude-haiku-4-5-20251001');
    Config::set('services.anthropic.max_tokens', 8192);
});

test('execute gibt erfolg mit content zurueck', function () {
    $mockService = Mockery::mock(ClaudeService::class);
    $mockService->shouldReceive('callByConfigKey')
        ->once()
        ->andReturn([
            'content' => 'KI-Antwort',
            'raw' => ['id' => 'msg-1'],
            'tokens_used' => 42,
        ]);

    $action = new SendAgentMessage($mockService);
    $result = $action->execute('search_agent', [
        ['role' => 'user', 'content' => 'Frage'],
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['content'])->toBe('KI-Antwort');
    expect($result)->toHaveKey('raw');
});

test('execute gibt fehlermeldung bei insufficient credits', function () {
    $mockService = Mockery::mock(ClaudeService::class);
    $mockService->shouldReceive('callByConfigKey')
        ->andThrow(new InsufficientCreditsException);

    $action = new SendAgentMessage($mockService);
    $result = $action->execute('search_agent', [['role' => 'user', 'content' => 'Test']]);

    expect($result['success'])->toBeFalse();
    expect($result['content'])->toContain('Guthaben');
});

test('execute gibt fehlermeldung bei claude agent fehler', function () {
    $mockService = Mockery::mock(ClaudeService::class);
    $mockService->shouldReceive('callByConfigKey')
        ->andThrow(new ClaudeAgentException('API Error'));

    $action = new SendAgentMessage($mockService);
    $result = $action->execute('search_agent', [['role' => 'user', 'content' => 'Test']]);

    expect($result['success'])->toBeFalse();
    expect($result['content'])->toContain('Fehler');
});

test('execute gibt fehlermeldung bei unerwarteter exception', function () {
    Log::spy();

    $mockService = Mockery::mock(ClaudeService::class);
    $mockService->shouldReceive('callByConfigKey')
        ->andThrow(new \RuntimeException('Connection refused'));

    $action = new SendAgentMessage($mockService);
    $result = $action->execute('search_agent', [['role' => 'user', 'content' => 'Test']]);

    expect($result['success'])->toBeFalse();
    expect($result['content'])->toContain('Verbindung');

    Log::shouldHaveReceived('error')
        ->once()
        ->with('SendAgentMessage: unerwartete Exception', Mockery::on(
            fn ($ctx) => $ctx['key'] === 'search_agent' && str_contains($ctx['message'], 'Connection refused')
        ));
});
