<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

// Hilfsfunktion: Autorisierungs-Header mit gültigem MCP-Token
function mcpHeaders(): array
{
    Config::set('services.mcp.auth_token', 'test-mcp-token');

    return ['Authorization' => 'Bearer test-mcp-token'];
}

function ollamaUrl(): string
{
    return config('services.ollama.url') . '/api/embeddings';
}

// --- Ingest ---

test('ingest rejects request without bearer token', function () {
    $response = $this->postJson('/api/papers/ingest', [
        'paper_id' => 'paper-1',
        'source' => 'pubmed',
        'title' => 'Test Paper',
        'text' => 'Some content',
    ]);

    $response->assertStatus(401);
});

test('ingest rejects request with invalid bearer token', function () {
    Config::set('services.mcp.auth_token', 'test-mcp-token');

    $response = $this->postJson('/api/papers/ingest', [
        'paper_id' => 'paper-1',
        'source' => 'pubmed',
        'title' => 'Test Paper',
        'text' => 'Some content',
    ], ['Authorization' => 'Bearer wrong-token']);

    $response->assertStatus(401);
});

test('ingest dispatches job with valid data', function () {
    Queue::fake();

    $response = $this->postJson('/api/papers/ingest', [
        'paper_id' => 'paper-1',
        'source' => 'pubmed',
        'title' => 'Test Paper',
        'text' => 'Some content about a study.',
    ], mcpHeaders());

    $response->assertStatus(200);
    $response->assertJson(['status' => 'queued']);
    Queue::assertPushed(\App\Jobs\IngestPaperJob::class);
});

test('ingest requires paper_id', function () {
    Queue::fake();

    $response = $this->postJson('/api/papers/ingest', [
        'source' => 'pubmed',
        'title' => 'Test Paper',
        'text' => 'Some content',
    ], mcpHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['paper_id']);
    Queue::assertNothingPushed();
});

test('ingest requires source', function () {
    Queue::fake();

    $response = $this->postJson('/api/papers/ingest', [
        'paper_id' => 'paper-1',
        'title' => 'Test Paper',
        'text' => 'Some content',
    ], mcpHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['source']);
    Queue::assertNothingPushed();
});

test('ingest requires title', function () {
    Queue::fake();

    $response = $this->postJson('/api/papers/ingest', [
        'paper_id' => 'paper-1',
        'source' => 'pubmed',
        'text' => 'Some content',
    ], mcpHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['title']);
    Queue::assertNothingPushed();
});

test('ingest requires text', function () {
    Queue::fake();

    $response = $this->postJson('/api/papers/ingest', [
        'paper_id' => 'paper-1',
        'source' => 'pubmed',
        'title' => 'Test Paper',
    ], mcpHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['text']);
    Queue::assertNothingPushed();
});

test('ingest accepts nullable projekt_id', function () {
    Queue::fake();

    $response = $this->postJson('/api/papers/ingest', [
        'paper_id' => 'paper-1',
        'source' => 'pubmed',
        'title' => 'Test Paper',
        'text' => 'Some content',
        'projekt_id' => null,
    ], mcpHeaders());

    $response->assertStatus(200);
    Queue::assertPushed(\App\Jobs\IngestPaperJob::class, function ($job) {
        return $job->projektId === null;
    });
});

test('ingest rejects non-uuid projekt_id', function () {
    Queue::fake();

    $response = $this->postJson('/api/papers/ingest', [
        'paper_id' => 'paper-1',
        'source' => 'pubmed',
        'title' => 'Test Paper',
        'text' => 'Some content',
        'projekt_id' => 'not-a-uuid',
    ], mcpHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['projekt_id']);
    Queue::assertNothingPushed();
});

test('ingest rejects non-existent projekt_id', function () {
    Queue::fake();

    $response = $this->postJson('/api/papers/ingest', [
        'paper_id' => 'paper-1',
        'source' => 'pubmed',
        'title' => 'Test Paper',
        'text' => 'Some content',
        'projekt_id' => fake()->uuid(), // valid UUID but not in DB
    ], mcpHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['projekt_id']);
    Queue::assertNothingPushed();
});

test('ingest passes metadata as array', function () {
    Queue::fake();

    $response = $this->postJson('/api/papers/ingest', [
        'paper_id' => 'paper-1',
        'source' => 'pubmed',
        'title' => 'Test Paper',
        'text' => 'Some content',
        'metadata' => ['year' => 2024, 'journal' => 'NEJM'],
    ], mcpHeaders());

    $response->assertStatus(200);
    Queue::assertPushed(\App\Jobs\IngestPaperJob::class, function ($job) {
        return $job->metadata === ['year' => 2024, 'journal' => 'NEJM'];
    });
});

// --- RAG-Suche ---

test('search rejects request without bearer token', function () {
    $response = $this->getJson('/api/papers/rag-search?q=test');

    $response->assertStatus(401);
});

test('search requires q parameter', function () {
    $response = $this->getJson('/api/papers/rag-search', mcpHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['q']);
});

test('search rejects max_results above 50', function () {
    Http::fake([
        ollamaUrl() => Http::response(
            ['embedding' => array_fill(0, 768, 0.1)],
            200
        ),
    ]);

    $response = $this->getJson('/api/papers/rag-search?q=test&max_results=100', mcpHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['max_results']);
});

test('search rejects max_results below 1', function () {
    $response = $this->getJson('/api/papers/rag-search?q=test&max_results=0', mcpHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['max_results']);
});

test('search returns 503 when ollama is unavailable', function () {
    Http::fake([
        ollamaUrl() => Http::response(null, 503),
    ]);

    $response = $this->getJson('/api/papers/rag-search?q=test', mcpHeaders());

    $response->assertStatus(503);
    $response->assertJson(['error' => 'Embedding service unavailable']);
});

test('search returns 503 when ollama connection fails', function () {
    Http::fake([
        ollamaUrl() => Http::response(null, 500),
    ]);

    $response = $this->getJson('/api/papers/rag-search?q=test', mcpHeaders());

    $response->assertStatus(503);
});
