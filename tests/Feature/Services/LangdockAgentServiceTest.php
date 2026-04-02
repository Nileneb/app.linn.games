<?php

use App\Services\LangdockAgentException;
use App\Services\LangdockAgentService;
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
});

test('call sends request to langdock api and returns content', function () {
    Http::fake([
        '*' => Http::response([
            'messages' => [['id' => 'r-1', 'role' => 'assistant', 'content' => 'Hier ist dein Strukturmodell.']],
        ], 200),
    ]);

    $service = new LangdockAgentService();
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

    $service = new LangdockAgentService();
    $result = $service->call('test-agent-uuid', [
        ['role' => 'user', 'content' => 'Test'],
    ]);

    expect($result['content'])->toBe('Fallback-Output.');
});

test('call throws LangdockAgentException on http error', function () {
    Http::fake([
        '*' => Http::response('Server Error', 500),
    ]);

    $service = new LangdockAgentService();
    $service->call('test-agent-uuid', [
        ['role' => 'user', 'content' => 'Test'],
    ]);
})->throws(LangdockAgentException::class);

test('call throws LangdockAgentException when api key is missing', function () {
    Config::set('services.langdock.api_key', null);

    $service = new LangdockAgentService();
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

    $service = new LangdockAgentService();
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

    $service = new LangdockAgentService();
    $service->callByConfigKey('unknown_agent', [
        ['role' => 'user', 'content' => 'Test'],
    ]);
})->throws(LangdockAgentException::class);
