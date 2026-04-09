<?php

use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // Set ollama URL for tests
    Config::set('services.ollama.url', 'http://localhost:11434');
    Http::preventStrayRequests();
});

test('generate() calls ollama and returns validated embedding', function () {
    Http::fake([
        'http://localhost:11434/api/embeddings' => Http::response([
            'embedding' => [0.1, 0.2, 0.3, 0.4, 0.5],
        ]),
    ]);

    $service = app(EmbeddingService::class);
    $embedding = $service->generate('test text');

    expect($embedding)
        ->toBeArray()
        ->toHaveCount(5)
        ->each->toBeFloat();

    Http::assertSent(function ($request) {
        return str_ends_with((string) $request->url(), '/api/embeddings')
            && $request->method() === 'POST'
            && $request['model'] === 'nomic-embed-text'
            && $request['prompt'] === 'test text';
    });
});

test('generate() throws when ollama returns 500', function () {
    Http::fake([
        'http://localhost:11434/api/embeddings' => Http::response([], 500),
    ]);

    $service = app(EmbeddingService::class);

    try {
        $service->generate('test text');
        throw new Exception('Should have thrown');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('Ollama returned 500');
    }
});

test('generate() throws when embedding is not array', function () {
    Http::fake([
        'http://localhost:11434/api/embeddings' => Http::response(['embedding' => 'not-an-array']),
    ]);

    $service = app(EmbeddingService::class);

    try {
        $service->generate('test text');
        throw new Exception('Should have thrown');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('Invalid embedding format');
    }
});

test('validate() filters out non-finite values', function () {
    $service = app(EmbeddingService::class);
    $embedding = $service->validate([0.1, \INF, 0.2, \NAN, 0.3]);

    expect($embedding)
        ->toBeArray()
        ->toHaveCount(3);

    // Note: array_filter keeps keys, so we need to check values
    $values = array_values($embedding);
    expect($values)->toBe([0.1, 0.2, 0.3]);
});

test('validate() throws when all values are filtered', function () {
    $service = app(EmbeddingService::class);

    try {
        $service->validate([\INF, \NAN, \NAN]);
        throw new Exception('Should have thrown');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('No valid embedding values');
    }
});

test('toLiteral() formats embedding correctly', function () {
    $service = app(EmbeddingService::class);
    $literal = $service->toLiteral([0.1, 0.2, 0.3]);

    expect($literal)->toBe('[0.1,0.2,0.3]');
});

test('toLiteral() handles various float formats', function () {
    $service = app(EmbeddingService::class);
    $literal = $service->toLiteral([0.0000001, 1000.5, -0.001]);

    // PHP may use scientific notation for very small numbers, so check values are present
    expect($literal)
        ->toMatch('/1\.0[eE]-7/')  // Scientific notation
        ->toContain('1000.5')
        ->toContain('-0.001');
});

test('generate() logs ollama errors', function () {
    Log::spy();

    Http::fake([
        'http://localhost:11434/api/embeddings' => Http::response(['error' => 'Model not found'], 404),
    ]);

    $service = app(EmbeddingService::class);

    try {
        $service->generate('test text');
    } catch (\RuntimeException) {
        // Expected
    }

    Log::shouldHaveReceived('error')
        ->withArgs(function ($message, $context) {
            return $message === 'Ollama embedding request failed'
                && $context['status'] === 404;
        });
});

test('validate() works with mixed numeric types', function () {
    $service = app(EmbeddingService::class);
    // Mix integers and floats
    $embedding = $service->validate([1, 2.5, 3, 4.7]);

    expect($embedding)
        ->toBeArray()
        ->toHaveCount(4)
        ->each->toBeFloat();
});
