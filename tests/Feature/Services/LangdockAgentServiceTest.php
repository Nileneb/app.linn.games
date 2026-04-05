<?php

use App\Services\LangdockAgentException;
use App\Services\LangdockAgentService;
use App\Services\LangdockContextInjector;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('services.langdock.base_url', 'https://api.langdock.com/agent/v1/chat/completions');
    Config::set('services.langdock.api_key', 'test-api-key');
    Config::set('services.langdock.agent_id', 'test-agent-uuid');
    Config::set('services.langdock.scoping_mapping_agent', 'scoping-uuid');
    Config::set('services.langdock.search_agent', 'search-uuid');
    Config::set('services.langdock.review_agent', 'review-uuid');
    Config::set('services.langdock.retrieval_agent', 'retrieval-uuid');
    // Kein echtes Warten in Tests
    Config::set('services.langdock.retry_attempts', 3);
    Config::set('services.langdock.retry_sleep_ms', 0);
});

function makeLangdockService(): LangdockAgentService
{
    return new LangdockAgentService(new LangdockContextInjector());
}

test('call sends request to langdock api and returns content', function () {
    Http::fake([
        '*' => Http::response([
            'messages' => [['id' => 'r-1', 'role' => 'assistant', 'content' => 'Hier ist dein Strukturmodell.']],
        ], 200),
    ]);

    $service = makeLangdockService();
    $result = $service->call('test-agent-uuid', [
        ['role' => 'user', 'content' => 'Analysiere diese Forschungsfrage.'],
    ]);

    expect($result['content'])->toBe('Hier ist dein Strukturmodell.');
    expect($result['raw'])->toBeArray();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.langdock.com/agent/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request['agentId'] === 'test-agent-uuid'
            && $request['messages'][0]['role'] === 'user'
            && $request['messages'][0]['parts'][0]['type'] === 'text'
            && $request['messages'][0]['parts'][0]['text'] === 'Analysiere diese Forschungsfrage.';
    });
});

test('call falls back to result array when messages content is missing', function () {
    Http::fake([
        '*' => Http::response([
            'result' => [['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Fallback-Output.']]]],
        ], 200),
    ]);

    $service = makeLangdockService();
    $result = $service->call('test-agent-uuid', [
        ['role' => 'user', 'content' => 'Test'],
    ]);

    expect($result['content'])->toBe('Fallback-Output.');
});

test('call throws LangdockAgentException on http error', function () {
    Http::fake([
        '*' => Http::response('Server Error', 500),
    ]);

    $service = makeLangdockService();
    $service->call('test-agent-uuid', [
        ['role' => 'user', 'content' => 'Test'],
    ]);
})->throws(LangdockAgentException::class);

test('call throws LangdockAgentException when api key is missing', function () {
    Config::set('services.langdock.api_key', null);

    $service = makeLangdockService();
    $service->call('test-agent-uuid', [
        ['role' => 'user', 'content' => 'Test'],
    ]);
})->throws(LangdockAgentException::class);

test('callByConfigKey resolves agent id from config', function () {
    Http::fake([
        '*' => Http::response([
            'messages' => [['id' => 'r-2', 'role' => 'assistant', 'content' => 'Suchstring generiert.']],
        ], 200),
    ]);

    $service = makeLangdockService();
    $result = $service->callByConfigKey('search_agent', [
        ['role' => 'user', 'content' => 'Generiere Suchstrings.'],
    ]);

    expect($result['content'])->toBe('Suchstring generiert.');

    Http::assertSent(function ($request) {
        return $request['agentId'] === 'search-uuid';
    });
});

test('callByConfigKey throws when config key is not set', function () {
    Config::set('services.langdock.unknown_agent', null);

    $service = makeLangdockService();
    $service->callByConfigKey('unknown_agent', [
        ['role' => 'user', 'content' => 'Test'],
    ]);
})->throws(LangdockAgentException::class);

test('call injects context message and metadata when projekt_id and user_id are given', function () {
    Http::fake([
        '*' => Http::response([
            'messages' => [['id' => 'r-3', 'role' => 'assistant', 'content' => 'OK.']],
        ], 200),
    ]);

    $projektId = '11111111-1111-1111-1111-111111111111';
    $userId    = '22222222-2222-2222-2222-222222222222';

    $service = makeLangdockService();
    $service->call('test-agent-uuid', [
        ['role' => 'user', 'content' => 'Analysiere.'],
    ], 120, ['projekt_id' => $projektId, 'user_id' => $userId]);

    Http::assertSent(function ($request) use ($projektId, $userId) {
        $messages = $request['messages'];

        // First message must be the injected context with RLS instruction and JSON context
        $contextText = $messages[0]['parts'][0]['text'] ?? '';
        $hasSetLocal = str_contains($contextText, "SET LOCAL app.current_projekt_id = '{$projektId}'");
        $hasKontext  = str_contains($contextText, '"projekt_id":"' . $projektId . '"');

        // Second message is the actual user input
        $hasUserMessage = ($messages[1]['parts'][0]['text'] ?? '') === 'Analysiere.';

        // Metadata must be present in body
        $hasMetadata = ($request['metadata']['projekt_id'] ?? null) === $projektId;

        return $hasSetLocal && $hasKontext && $hasUserMessage && $hasMetadata;
    });
});

test('call does not inject context message when no projekt_id or user_id given', function () {
    Http::fake([
        '*' => Http::response([
            'messages' => [['id' => 'r-4', 'role' => 'assistant', 'content' => 'OK.']],
        ], 200),
    ]);

    $service = makeLangdockService();
    $service->call('test-agent-uuid', [
        ['role' => 'user', 'content' => 'Test ohne Kontext.'],
    ]);

    Http::assertSent(function ($request) {
        $messages = $request['messages'];

        return count($messages) === 1
            && ($messages[0]['parts'][0]['text'] ?? '') === 'Test ohne Kontext.'
            && ! isset($request['metadata']);
    });
});

// ---------------------------------------------------------------------------
// Retry-Logik
// ---------------------------------------------------------------------------

test('call retries on 5xx and succeeds on second attempt', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push('Server Error', 503)
            ->push(['messages' => [['id' => 'r-ok', 'role' => 'assistant', 'content' => 'Retry erfolgreich.']]], 200),
    ]);

    $service = new LangdockAgentService(new LangdockContextInjector());
    $result = $service->call('test-agent-uuid', [
        ['role' => 'user', 'content' => 'Test'],
    ]);

    expect($result['content'])->toBe('Retry erfolgreich.');
    Http::assertSentCount(2);
});

test('call throws after exhausting all retries on persistent 5xx', function () {
    Http::fake([
        '*' => Http::response('Service Unavailable', 503),
    ]);

    $service = new LangdockAgentService(new LangdockContextInjector());
    $service->call('test-agent-uuid', [
        ['role' => 'user', 'content' => 'Test'],
    ]);
})->throws(LangdockAgentException::class);

test('call does not retry on 4xx client errors', function () {
    Http::fake([
        '*' => Http::response('Unauthorized', 401),
    ]);

    $service = new LangdockAgentService(new LangdockContextInjector());

    try {
        $service->call('test-agent-uuid', [['role' => 'user', 'content' => 'Test']]);
    } catch (LangdockAgentException $e) {
        // erwartet
    }

    // 401 darf nicht wiederholt werden
    Http::assertSentCount(1);
});

test('retry_attempts config controls number of retries', function () {
    Config::set('services.langdock.retry_attempts', 1);

    Http::fake([
        '*' => Http::response('Server Error', 500),
    ]);

    $service = new LangdockAgentService(new LangdockContextInjector());

    try {
        $service->call('test-agent-uuid', [['role' => 'user', 'content' => 'Test']]);
    } catch (LangdockAgentException $e) {
        // erwartet
    }

    // 1 Originalversuch + 1 Retry = 2 Requests
    Http::assertSentCount(2);
});

// ---------------------------------------------------------------------------
// Token-Schätzung
// ---------------------------------------------------------------------------

test('estimateTokens accounts for non-ascii characters', function () {
    $service = new LangdockAgentService(new LangdockContextInjector());
    $reflection = new \ReflectionMethod($service, 'estimateTokens');
    $reflection->setAccessible(true);

    $asciiOnly = [['parts' => [['text' => str_repeat('a', 200)]]]]; // 200 ASCII chars
    $withCjk   = [['parts' => [['text' => str_repeat('字', 200)]]]]; // 200 CJK chars

    $asciiTokens = $reflection->invoke($service, $asciiOnly);
    $cjkTokens   = $reflection->invoke($service, $withCjk);

    // CJK sollte mehr Token schätzen als gleich viele ASCII-Zeichen
    expect($cjkTokens)->toBeGreaterThan($asciiTokens);
});

test('estimateTokens adds per-message overhead', function () {
    $service = new LangdockAgentService(new LangdockContextInjector());
    $reflection = new \ReflectionMethod($service, 'estimateTokens');
    $reflection->setAccessible(true);

    $oneMsg  = [['parts' => [['text' => 'Hello']]]];
    $twoMsgs = [['parts' => [['text' => 'Hello']]], ['parts' => [['text' => 'Hello']]]];

    $one = $reflection->invoke($service, $oneMsg);
    $two = $reflection->invoke($service, $twoMsgs);

    // Zwei gleiche Nachrichten müssen mehr Token schätzen als eine
    expect($two)->toBeGreaterThan($one);
});
