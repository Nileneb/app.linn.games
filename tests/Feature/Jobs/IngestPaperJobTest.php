<?php

use App\Jobs\IngestPaperJob;
use App\Models\Recherche\Projekt;
use App\Services\EmbeddingService;
use App\Services\MayringMcpClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $mcpMock = Mockery::mock(MayringMcpClient::class);
    $mcpMock->shouldReceive('ingestAndCategorize')->andReturn(null)->byDefault();
    app()->instance(MayringMcpClient::class, $mcpMock);
});

function fakeEmbedding(): array
{
    return array_fill(0, 768, 0.1);
}

test('job chunks text and inserts embeddings into paper_embeddings', function () {
    $embeddingMock = Mockery::mock(EmbeddingService::class);
    $embeddingMock->shouldReceive('generate')->andReturn(fakeEmbedding());
    $embeddingMock->shouldReceive('toLiteral')->andReturnUsing(
        fn (array $e) => '['.implode(',', $e).']'
    );
    app()->instance(EmbeddingService::class, $embeddingMock);

    $text = implode(' ', array_fill(0, 600, 'word'));

    (new IngestPaperJob(
        paperId: 'doi:10.1234/test',
        source: 'fulltext',
        title: 'Test Paper',
        text: $text,
    ))->handle();

    expect(DB::table('paper_embeddings')->where('paper_id', 'doi:10.1234/test')->count())
        ->toBeGreaterThan(0);
});

test('job creates multiple chunks for long text', function () {
    $embeddingMock = Mockery::mock(EmbeddingService::class);
    $embeddingMock->shouldReceive('generate')->andReturn(fakeEmbedding());
    $embeddingMock->shouldReceive('toLiteral')->andReturnUsing(
        fn (array $e) => '['.implode(',', $e).']'
    );
    app()->instance(EmbeddingService::class, $embeddingMock);

    // 1200 Wörter → mindestens 2 Chunks (500 Wörter, 100er Overlap)
    $text = implode(' ', array_fill(0, 1200, 'word'));

    (new IngestPaperJob(
        paperId: 'doi:10.5678/multi',
        source: 'fulltext',
        title: 'Multi-Chunk Paper',
        text: $text,
    ))->handle();

    expect(DB::table('paper_embeddings')->where('paper_id', 'doi:10.5678/multi')->count())
        ->toBeGreaterThan(1);
});

test('job stores projekt_id and metadata when provided', function () {
    $embeddingMock = Mockery::mock(EmbeddingService::class);
    $embeddingMock->shouldReceive('generate')->andReturn(fakeEmbedding());
    $embeddingMock->shouldReceive('toLiteral')->andReturnUsing(
        fn (array $e) => '['.implode(',', $e).']'
    );
    app()->instance(EmbeddingService::class, $embeddingMock);

    $projekt = Projekt::factory()->create();
    $projektId = $projekt->id;

    (new IngestPaperJob(
        paperId: 'doi:10.9999/meta',
        source: 'abstract',
        title: 'Meta Paper',
        text: 'Short abstract text',
        projektId: $projektId,
        metadata: ['source_db' => 'pubmed'],
    ))->handle();

    $row = DB::table('paper_embeddings')->where('paper_id', 'doi:10.9999/meta')->first();
    expect($row)->not->toBeNull()
        ->and($row->projekt_id)->toBe($projektId)
        ->and(json_decode($row->metadata, true)['source_db'])->toBe('pubmed');
});

test('job handles empty text gracefully without inserting rows', function () {
    $embeddingMock = Mockery::mock(EmbeddingService::class);
    $embeddingMock->shouldReceive('generate')->never();
    app()->instance(EmbeddingService::class, $embeddingMock);

    (new IngestPaperJob(
        paperId: 'doi:10.0000/empty',
        source: 'fulltext',
        title: 'Empty Paper',
        text: '',
    ))->handle();

    expect(DB::table('paper_embeddings')->where('paper_id', 'doi:10.0000/empty')->count())->toBe(0);
});

test('job rolls back all inserts on embedding failure', function () {
    $embeddingMock = Mockery::mock(EmbeddingService::class);
    $embeddingMock->shouldReceive('generate')->once()->andThrow(new \RuntimeException('Ollama unavailable'));
    app()->instance(EmbeddingService::class, $embeddingMock);

    expect(fn () => (new IngestPaperJob(
        paperId: 'doi:10.1111/fail',
        source: 'fulltext',
        title: 'Fail Paper',
        text: implode(' ', array_fill(0, 100, 'word')),
    ))->handle())->toThrow(\RuntimeException::class);

    expect(DB::table('paper_embeddings')->where('paper_id', 'doi:10.1111/fail')->count())->toBe(0);
});
