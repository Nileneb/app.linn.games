<?php

use App\Services\MayringMcpClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.mayring_mcp.endpoint' => 'http://mayring-test.local',
        'services.mayring_mcp.auth_token' => 'test-token',
    ]);
    \Illuminate\Support\Facades\Cache::flush();
});

test('searchDocuments sendet korrekte anfrage', function () {
    Http::fake([
        'http://mayring-test.local/search*' => Http::response([
            'results' => [
                ['chunk_id' => 'c1', 'text' => 'Relevanter Text', 'similarity' => 0.92],
            ],
            'prompt_context' => 'Relevanter Text',
        ], 200),
    ]);

    $client = app(MayringMcpClient::class);
    $result = $client->searchDocuments('KI Pflege');

    expect($result)->toHaveKey('results');
    expect($result['results'][0]['chunk_id'])->toBe('c1');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/search')
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->data()['query'] === 'KI Pflege';
    });
});

test('ingestAndCategorize sendet content und source_id', function () {
    Http::fake([
        'http://mayring-test.local/ingest*' => Http::response([
            'source_id' => 'src-1',
            'chunk_ids' => ['c1', 'c2'],
            'indexed' => 2,
        ], 200),
    ]);

    $result = app(MayringMcpClient::class)->ingestAndCategorize('Studieninhalt...', 'studie-123');

    expect($result)->toHaveKey('chunk_ids');
    Http::assertSent(fn ($r) => $r->data()['categorize'] === true);
});

test('searchDocuments wirft exception bei server-fehler', function () {
    Http::fake(['http://mayring-test.local/*' => Http::response([], 500)]);

    expect(fn () => app(MayringMcpClient::class)->searchDocuments('test'))
        ->toThrow(\RuntimeException::class);
});
