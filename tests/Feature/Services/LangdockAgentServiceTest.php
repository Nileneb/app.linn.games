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

test('call injects context message and metadata when projekt_id and user_id are given', function () {
    Http::fake([
        '*' => Http::response([
            'messages' => [['id' => 'r-3', 'role' => 'assistant', 'content' => 'OK.']],
        ], 200),
    ]);

    $projektId = '11111111-1111-1111-1111-111111111111';
    $userId    = 42;

    $service = new LangdockAgentService();
    $service->call('test-agent-uuid', [
        ['role' => 'user', 'content' => 'Analysiere.'],
    ], 120, ['projekt_id' => $projektId, 'user_id' => $userId]);

    Http::assertSent(function ($request) use ($projektId, $userId) {
        $messages = $request['messages'];

        // First message must be the injected _context
        $contextText = $messages[0]['parts'][0]['text'] ?? '';
        $context     = json_decode($contextText, true)['_context'] ?? [];

        $hasContextMessage = $context['projekt_id'] === $projektId
            && (int) $context['user_id'] === $userId;

        // Second message is the actual user input
        $hasUserMessage = ($messages[1]['parts'][0]['text'] ?? '') === 'Analysiere.';

        // Metadata must be present in body
        $hasMetadata = ($request['metadata']['projekt_id'] ?? null) === $projektId;

        return $hasContextMessage && $hasUserMessage && $hasMetadata;
    });
});

test('call does not inject context message when no projekt_id or user_id given', function () {
    Http::fake([
        '*' => Http::response([
            'messages' => [['id' => 'r-4', 'role' => 'assistant', 'content' => 'OK.']],
        ], 200),
    ]);

    $service = new LangdockAgentService();
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
