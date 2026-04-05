<?php

use App\Models\Recherche\Projekt;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\RetrieverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

test('retrieve gibt leeres Array zurück wenn keine Embeddings für Projekt vorhanden', function () {
    $service = new RetrieverService(app(EmbeddingService::class));

    $result = $service->retrieve('Forschungsfrage', '00000000-0000-0000-0000-000000000001');

    expect($result)->toBeArray()->toBeEmpty();
});

test('retrieve gibt leeres Array zurück wenn EmbeddingService fehlschlägt', function () {
    Log::spy();

    $embedding = $this->mock(EmbeddingService::class, function ($mock) {
        $mock->shouldReceive('generate')->andThrow(new \RuntimeException('Ollama down'));
    });

    $user    = User::factory()->withoutTwoFactor()->create();
    $projekt = Projekt::factory()->create(['user_id' => $user->id]);

    DB::table('paper_embeddings')->insert([
        'id'          => \Illuminate\Support\Str::uuid(),
        'projekt_id'  => $projekt->id,
        'source'      => 'test',
        'paper_id'    => 'paper_1',
        'title'       => 'Test Paper',
        'chunk_index' => 0,
        'text_chunk'  => 'Beispieltext',
        'erstellt_am' => now(),
    ]);

    $service = new RetrieverService($embedding);

    $result = $service->retrieve('Forschungsfrage', $projekt->id);

    expect($result)->toBeArray()->toBeEmpty();

    Log::shouldHaveReceived('warning')->once()->with(
        'RetrieverService: Abruf fehlgeschlagen, fahre ohne Retriever fort',
        \Mockery::any()
    );
});

test('formatAsContext gibt leeren String zurück für leere Chunks', function () {
    $service = new RetrieverService(app(EmbeddingService::class));

    expect($service->formatAsContext([]))->toBe('');
});

test('formatAsContext formatiert Chunks korrekt', function () {
    $service = new RetrieverService(app(EmbeddingService::class));

    $chunks = [
        (object) [
            'paper_id'    => 'p1',
            'title'       => 'Studie über X',
            'chunk_index' => 2,
            'text_chunk'  => 'Dieser Abschnitt beschreibt Methodik.',
            'similarity'  => 0.92,
        ],
    ];

    $result = $service->formatAsContext($chunks);

    expect($result)
        ->toContain('RELEVANTE DOKUMENT-ABSCHNITTE')
        ->toContain('Studie über X')
        ->toContain('Abschnitt 2')
        ->toContain('0.92')
        ->toContain('Dieser Abschnitt beschreibt Methodik.');
});
