<?php

use App\Jobs\DownloadPaperJob;
use App\Jobs\IngestPaperJob;
use App\Models\Recherche\P5Treffer;
use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Services\PdfParserService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
});

function makeTreffer(array $attrs = []): P5Treffer
{
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::create([
        'user_id' => $user->id,
        'workspace_id' => $user->ensureDefaultWorkspace()->id,
        'titel' => 'Test',
        'status' => 'aktiv',
    ]);

    return P5Treffer::create(array_merge([
        'projekt_id' => $projekt->id,
        'record_id' => 'rec-001',
        'titel' => 'Test Paper',
        'doi' => '10.1234/test',
        'datenbank_quelle' => 'pubmed',
        'retrieval_status' => null,
    ], $attrs));
}

test('job setzt status auf pending wenn treffer mit doi angelegt wird', function () {
    Http::fake(); // Observer feuert → Job dispatcht, wir prüfen nur den pending-Status
    Queue::fake();

    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::create([
        'user_id' => $user->id,
        'workspace_id' => $user->ensureDefaultWorkspace()->id,
        'titel' => 'Test',
        'status' => 'aktiv',
    ]);

    $treffer = P5Treffer::create([
        'projekt_id' => $projekt->id,
        'record_id' => 'rec-002',
        'titel' => 'Observer Test',
        'doi' => '10.9999/obs',
        'datenbank_quelle' => 'test',
    ]);

    expect($treffer->fresh()->retrieval_status)->toBe('pending');
    Queue::assertPushed(DownloadPaperJob::class);
});

test('job tut nichts wenn kein doi vorhanden', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::create([
        'user_id' => $user->id,
        'workspace_id' => $user->ensureDefaultWorkspace()->id,
        'titel' => 'Test',
        'status' => 'aktiv',
    ]);

    Queue::fake();

    P5Treffer::create([
        'projekt_id' => $projekt->id,
        'record_id' => 'rec-nodoi',
        'titel' => 'Kein DOI',
        'datenbank_quelle' => 'test',
    ]);

    Queue::assertNotPushed(DownloadPaperJob::class);
});

test('job lädt pdf herunter und startet ingest wenn unpaywall url liefert', function () {
    $treffer = makeTreffer(['retrieval_status' => 'pending']);
    $pdfContent = '%PDF-1.4 fake content for testing';

    Http::fake([
        'api.unpaywall.org/*' => Http::response([
            'best_oa_location' => ['url_for_pdf' => 'https://example.com/paper.pdf'],
        ], 200),
        'example.com/paper.pdf' => Http::response($pdfContent, 200),
    ]);

    // Mock PdfParserService to return actual text so IngestPaperJob gets dispatched
    $mock = Mockery::mock(PdfParserService::class);
    $mock->shouldReceive('extractText')->andReturn('Extracted paper text content');
    app()->instance(PdfParserService::class, $mock);

    (new DownloadPaperJob($treffer->id))->handle();

    $treffer->refresh();
    expect($treffer->retrieval_downloaded)->toBeTrue();
    expect($treffer->retrieval_status)->toBe('heruntergeladen');
    expect($treffer->retrieval_source_url)->toBe('https://example.com/paper.pdf');
    expect($treffer->retrieval_storage_path)->toStartWith('papers/');

    Storage::assertExists($treffer->retrieval_storage_path);
});

test('job nutzt abstract wenn unpaywall kein pdf hat', function () {
    $treffer = makeTreffer(['retrieval_status' => 'pending', 'abstract' => 'This study examines the use of AI in nursing homes.']);

    Http::fake([
        'api.unpaywall.org/*' => Http::response([
            'best_oa_location' => null,
        ], 200),
    ]);

    Queue::fake([IngestPaperJob::class]);

    (new DownloadPaperJob($treffer->id))->handle();

    $treffer->refresh();
    expect($treffer->retrieval_status)->toBe('abstract_only');
    Queue::assertPushed(IngestPaperJob::class);
});

test('job setzt bibliothek_erforderlich wenn weder pdf noch abstract', function () {
    $treffer = makeTreffer(['retrieval_status' => 'pending', 'abstract' => null]);

    Http::fake([
        'api.unpaywall.org/*' => Http::response([
            'best_oa_location' => null,
        ], 200),
    ]);

    (new DownloadPaperJob($treffer->id))->handle();

    $treffer->refresh();
    expect($treffer->retrieval_status)->toBe('bibliothek_erforderlich');
    Queue::assertNotPushed(IngestPaperJob::class);
});

test('job nutzt abstract wenn pdf-download fehlschlägt', function () {
    $treffer = makeTreffer(['retrieval_status' => 'pending', 'abstract' => 'Fallback abstract text here.']);

    Http::fake([
        'api.unpaywall.org/*' => Http::response([
            'best_oa_location' => ['url_for_pdf' => 'https://example.com/paper.pdf'],
        ], 200),
        'example.com/paper.pdf' => Http::response('', 503),
    ]);

    Queue::fake([IngestPaperJob::class]);

    (new DownloadPaperJob($treffer->id))->handle();

    $treffer->refresh();
    expect($treffer->retrieval_status)->toBe('abstract_only');
    Queue::assertPushed(IngestPaperJob::class);
});

test('job setzt bibliothek_erforderlich wenn unpaywall api nicht erreichbar und kein abstract', function () {
    $treffer = makeTreffer(['retrieval_status' => 'pending', 'abstract' => null]);

    Http::fake([
        'api.unpaywall.org/*' => Http::response(null, 500),
    ]);

    (new DownloadPaperJob($treffer->id))->handle();

    $treffer->refresh();
    expect($treffer->retrieval_status)->toBe('bibliothek_erforderlich');
});

test('job setzt text_extraktion_fehlgeschlagen wenn pdf text leer', function () {
    $treffer = makeTreffer(['retrieval_status' => 'pending']);
    $pdfContent = '%PDF-1.4 fake content for testing';

    Http::fake([
        'api.unpaywall.org/*' => Http::response([
            'best_oa_location' => ['url_for_pdf' => 'https://example.com/paper.pdf'],
        ], 200),
        'example.com/paper.pdf' => Http::response($pdfContent, 200),
    ]);

    // Mock PdfParserService to return empty text
    $mock = Mockery::mock(PdfParserService::class);
    $mock->shouldReceive('extractText')->andReturn('');
    app()->instance(PdfParserService::class, $mock);

    (new DownloadPaperJob($treffer->id))->handle();

    $treffer->refresh();
    expect($treffer->retrieval_downloaded)->toBeTrue();
    expect($treffer->retrieval_status)->toBe('text_extraktion_fehlgeschlagen');
    Queue::assertNotPushed(IngestPaperJob::class);
});
