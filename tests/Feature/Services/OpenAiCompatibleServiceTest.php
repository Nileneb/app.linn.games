<?php

use App\Services\OpenAiCompatibleService;
use Illuminate\Support\Facades\Http;

test('chat POSTs to /v1/chat/completions with bearer token', function () {
    Http::fake([
        'http://ollama.test/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Hallo Welt']]],
        ], 200),
    ]);

    $result = app(OpenAiCompatibleService::class)->chat(
        endpoint: 'http://ollama.test',
        apiKey: 'ollama-secret',
        model: 'qwen2.5:7b',
        systemPrompt: 'You are a helper.',
        userMessage: 'ping',
    );

    expect($result['content'])->toBe('Hallo Welt');

    Http::assertSent(function ($request) {
        return $request->url() === 'http://ollama.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer ollama-secret')
            && $request->data()['model'] === 'qwen2.5:7b'
            && count($request->data()['messages']) === 2;
    });
});

test('chat works without api key (local ollama)', function () {
    Http::fake([
        'http://localhost:11434/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
        ], 200),
    ]);

    $result = app(OpenAiCompatibleService::class)->chat(
        endpoint: 'http://localhost:11434',
        apiKey: null,
        model: 'llama3.2',
        systemPrompt: '',
        userMessage: 'hi',
    );

    expect($result['content'])->toBe('ok');

    Http::assertSent(function ($request) {
        return ! $request->hasHeader('Authorization')
            && count($request->data()['messages']) === 1;
    });
});

test('chat throws RuntimeException on HTTP error', function () {
    Http::fake([
        'http://bad.test/v1/chat/completions' => Http::response(['error' => 'Model not found'], 404),
    ]);

    expect(fn () => app(OpenAiCompatibleService::class)->chat(
        endpoint: 'http://bad.test',
        apiKey: null,
        model: 'nonexistent',
        systemPrompt: '',
        userMessage: 'test',
    ))->toThrow(RuntimeException::class);
});
