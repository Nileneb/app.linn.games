<?php

use App\Jobs\IngestPaperJob;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Config::set('services.mcp.auth_token', 'test-mcp-token');
});

test('search accepts offset parameter for pagination', function () {
    Http::fake([
        config('services.ollama.url') . '/api/embeddings' => Http::response([
            'embedding' => array_fill(0, 768, 0.5),
        ]),
    ]);

    // Setup test data
    for ($i = 0; $i < 10; $i++) {
        \DB::table('paper_embeddings')->insert([
            'id'            => \Illuminate\Support\Str::uuid()->toString(),
            'projekt_id'    => null,
            'source'        => 'test',
            'paper_id'      => "paper-{$i}",
            'title'         => "Paper {$i}",
            'chunk_index'   => 0,
            'text_chunk'    => "Content {$i}",
            'metadata'      => json_encode([]),
            'embedding'     => '[' . implode(',', array_fill(0, 768, 0.5)) . ']',
        ]);
    }

    $response1 = test()->withHeader('Authorization', 'Bearer test-mcp-token')
        ->getJson('/api/papers/rag-search?q=test&max_results=3&offset=0');

    expect($response1->status())->toBe(200);
    expect(count($response1->json()))->toBeLessThanOrEqual(3);

    $response2 = test()->withHeader('Authorization', 'Bearer test-mcp-token')
        ->getJson('/api/papers/rag-search?q=test&max_results=3&offset=3');

    expect($response2->status())->toBe(200);
    expect(count($response2->json()))->toBeLessThanOrEqual(3);
});

test('search defaults max_results correctly with parentheses', function () {
    Http::fake([
        config('services.ollama.url') . '/api/embeddings' => Http::response([
            'embedding' => array_fill(0, 768, 0.5),
        ]),
    ]);

    for ($i = 0; $i < 10; $i++) {
        \DB::table('paper_embeddings')->insert([
            'id'            => \Illuminate\Support\Str::uuid()->toString(),
            'projekt_id'    => null,
            'source'        => 'test',
            'paper_id'      => "paper-{$i}",
            'title'         => "Paper {$i}",
            'chunk_index'   => 0,
            'text_chunk'    => "Content {$i}",
            'metadata'      => json_encode([]),
            'embedding'     => '[' . implode(',', array_fill(0, 768, 0.5)) . ']',
        ]);
    }

    $response = test()->withHeader('Authorization', 'Bearer test-mcp-token')
        ->getJson('/api/papers/rag-search?q=test');

    expect($response->status())->toBe(200);
    expect(count($response->json()))->toBeLessThanOrEqual(5);
});

test('search returns 503 when ollama service unavailable', function () {
    Http::fake([
        config('services.ollama.url') . '/api/embeddings' => Http::response(null, 503),
    ]);

    $response = test()->withHeader('Authorization', 'Bearer test-mcp-token')
        ->getJson('/api/papers/rag-search?q=test');

    expect($response->status())->toBe(503);
    expect($response->json('error'))->toBe('Embedding service unavailable');
    expect($response->json('details'))->toContain('503');
});

test('search returns 503 when embedding response format is invalid', function () {
    Http::fake([
        config('services.ollama.url') . '/api/embeddings' => Http::response([
            'embedding' => 'not_array',
        ]),
    ]);

    $response = test()->withHeader('Authorization', 'Bearer test-mcp-token')
        ->getJson('/api/papers/rag-search?q=test');

    expect($response->status())->toBe(503);
    expect($response->json('error'))->toBe('Embedding service unavailable');
    expect($response->json('details'))->toContain('Invalid embedding format');
});

test('search returns 401 without bearer token', function () {
    Http::fake([
        config('services.ollama.url') . '/api/embeddings' => Http::response([
            'embedding' => array_fill(0, 768, 0.5),
        ]),
    ]);

    $response = test()->getJson('/api/papers/rag-search?q=test');

    expect($response->status())->toBe(401);
});

test('ingest queues job successfully', function () {
    Queue::fake();

    $response = test()->withHeader('Authorization', 'Bearer test-mcp-token')
        ->postJson('/api/papers/ingest', [
            'paper_id' => 'paper-123',
            'source'   => 'pubmed',
            'title'    => 'Test Paper',
            'text'     => 'Content here',
        ]);

    expect($response->status())->toBe(200);
    expect($response->json('status'))->toBe('queued');
    Queue::assertPushed(IngestPaperJob::class);
});

test('search rejects query exceeding 2000 characters', function () {
    $response = test()->withHeader('Authorization', 'Bearer test-mcp-token')
        ->getJson('/api/papers/rag-search?q=' . str_repeat('a', 2001));

    expect($response->status())->toBe(422);
});

test('search rejects max_results above 50', function () {
    $response = test()->withHeader('Authorization', 'Bearer test-mcp-token')
        ->getJson('/api/papers/rag-search?q=test&max_results=51');

    expect($response->status())->toBe(422);
});

test('ingest rejects missing required fields', function () {
    Queue::fake();

    $response = test()->withHeader('Authorization', 'Bearer test-mcp-token')
        ->postJson('/api/papers/ingest', [
            'paper_id' => 'paper-123',
            // source, title, text missing
        ]);

    expect($response->status())->toBe(422);
    Queue::assertNothingPushed();
});

test('ingest rejects invalid projekt_id', function () {
    Queue::fake();

    $response = test()->withHeader('Authorization', 'Bearer test-mcp-token')
        ->postJson('/api/papers/ingest', [
            'paper_id'   => 'paper-123',
            'source'     => 'pubmed',
            'title'      => 'Test',
            'text'       => 'Content',
            'projekt_id' => 'not-a-uuid',
        ]);

    expect($response->status())->toBe(422);
    Queue::assertNothingPushed();
});
