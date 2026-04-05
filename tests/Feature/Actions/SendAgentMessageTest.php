<?php

use App\Actions\SendAgentMessage;
use App\Services\InsufficientCreditsException;
use App\Services\LangdockAgentException;
use App\Services\LangdockAgentService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Config::set('services.langdock.api_key', 'test-api-key');
    Config::set('services.langdock.search_agent', 'search-uuid');
});

test('execute gibt erfolg mit content zurueck', function () {
    Http::fake([
        '*' => Http::response([
            'messages' => [['id' => 'r-1', 'role' => 'assistant', 'content' => 'KI-Antwort']],
        ], 200),
    ]);

    $result = app(SendAgentMessage::class)->execute('search_agent', [
        ['role' => 'user', 'content' => 'Frage'],
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['content'])->toBe('KI-Antwort');
    expect($result)->toHaveKey('raw');
});

test('execute gibt fehlermeldung bei insufficient credits', function () {
    $mockService = Mockery::mock(LangdockAgentService::class);
    $mockService->shouldReceive('callByConfigKey')
        ->andThrow(new InsufficientCreditsException);

    $action = new SendAgentMessage($mockService);
    $result = $action->execute('search_agent', [['role' => 'user', 'content' => 'Test']]);

    expect($result['success'])->toBeFalse();
    expect($result['content'])->toContain('Guthaben');
});

test('execute gibt fehlermeldung bei langdock api fehler', function () {
    $mockService = Mockery::mock(LangdockAgentService::class);
    $mockService->shouldReceive('callByConfigKey')
        ->andThrow(new LangdockAgentException('API Error'));

    $action = new SendAgentMessage($mockService);
    $result = $action->execute('search_agent', [['role' => 'user', 'content' => 'Test']]);

    expect($result['success'])->toBeFalse();
    expect($result['content'])->toContain('Fehler');
});

test('execute gibt fehlermeldung bei unerwarteter exception', function () {
    Log::spy();

    $mockService = Mockery::mock(LangdockAgentService::class);
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
