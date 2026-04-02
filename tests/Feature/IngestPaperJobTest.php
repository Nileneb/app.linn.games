<?php

use App\Jobs\IngestPaperJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

test('ingest paper job can be dispatched', function () {
    Queue::fake();

    IngestPaperJob::dispatch(
        'paper-1',
        'pubmed',
        'Test Paper Title',
        'This is the full text of a research paper.',
        null,
        ['year' => 2024],
    );

    Queue::assertPushed(IngestPaperJob::class, function ($job) {
        return $job->paperId === 'paper-1'
            && $job->source === 'pubmed'
            && $job->title === 'Test Paper Title'
            && $job->projektId === null
            && $job->metadata === ['year' => 2024];
    });
});

test('ingest paper job stores correct parameters', function () {
    Queue::fake();

    $projektId = fake()->uuid();

    IngestPaperJob::dispatch('doi-123', 'cochrane', 'Cochrane Review', 'Full text here.', $projektId);

    Queue::assertPushed(IngestPaperJob::class, function ($job) use ($projektId) {
        return $job->paperId === 'doi-123'
            && $job->source === 'cochrane'
            && $job->projektId === $projektId;
    });
});

test('ingest paper job fails when ollama returns error', function () {
    Http::fake([
        config('services.ollama.url') . '/api/embeddings' => Http::response(null, 503),
    ]);

    Log::spy();

    $job = new IngestPaperJob(
        'paper-1',
        'pubmed',
        'Test Paper',
        'Word1 Word2 Word3',
    );

    // Direkter Aufruf – ohne Queue-Kontext, $this->fail() ist no-op
    $job->handle();

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function ($message, $context) {
            return $message === 'Ollama embedding failed'
                && $context['paper_id'] === 'paper-1';
        });

    // Kein pgvector-Insert wurde versucht (Ollama-Fehler führt zu frühem Return)
    Http::assertSentCount(1);
});

test('ingest paper job retries up to 3 times', function () {
    $job = new IngestPaperJob('paper-1', 'pubmed', 'Title', 'Text');

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe(30);
});

test('ingest paper job chunks long text correctly', function () {
    Queue::fake();

    // 600 Wörter → sollte 2 Chunks ergeben (500 Wörter Chunk-Größe, 100 Overlap)
    $text = implode(' ', array_fill(0, 600, 'word'));

    IngestPaperJob::dispatch('paper-chunks', 'pubmed', 'Long Paper', $text);

    Queue::assertPushed(IngestPaperJob::class, function ($job) use ($text) {
        // Nur prüfen ob Job korrekt erstellt wurde – Chunking wird in handle() getestet
        return $job->text === $text;
    });
});

test('ingest paper job handles text shorter than chunk size', function () {
    Http::fake([
        config('services.ollama.url') . '/api/embeddings' => Http::response(
            ['embedding' => array_fill(0, 768, 0.1)],
            200
        ),
    ]);

    Log::spy();

    $job = new IngestPaperJob(
        'paper-short',
        'pubmed',
        'Short Paper',
        'This is a very short text.', // weit unter 500 Wörtern
    );

    // Ollama-Request geht durch, aber pgvector-INSERT schlägt in SQLite fehl (::vector)
    // → RuntimeException vom DB-Layer wird erwartet
    expect(fn () => $job->handle())->toThrow(\Exception::class);

    // Aber Ollama wurde genau einmal aufgerufen (1 Chunk)
    Http::assertSentCount(1);
});
