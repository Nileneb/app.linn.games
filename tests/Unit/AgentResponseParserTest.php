<?php

use App\Services\AgentResponseParser;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->parser = new AgentResponseParser;
});

test('parst Standard-Envelope mit meta/result/db_payload', function () {
    $json = json_encode([
        'meta' => ['phase' => 1, 'status' => 'completed'],
        'result' => [
            'summary' => 'Analyse abgeschlossen',
            'data' => [
                'md_files' => [
                    ['path' => 'synthesis_p1.md', 'content' => '# Synthese'],
                ],
            ],
        ],
        'db_payload' => [
            'tables' => [
                'p1_strukturmodelle' => [
                    ['komponente' => 'Population', 'wert' => 'Erwachsene'],
                ],
            ],
        ],
    ]);

    $result = $this->parser->parse($json);

    expect($result['content'])->toBe('Analyse abgeschlossen');
    expect($result['db_payload'])->toHaveKey('tables');
    expect($result['md_files'])->toHaveCount(1);
    expect($result['meta']['phase'])->toBe(1);
});

test('parst Flat-Struktur mit data key', function () {
    $json = json_encode([
        'data' => [
            'summary' => 'Flat result',
            'md_files' => [
                ['path' => 'output.md', 'content' => '# Output'],
            ],
        ],
    ]);

    $result = $this->parser->parse($json);

    expect($result['content'])->toBe('Flat result');
    expect($result['md_files'])->toHaveCount(1);
    expect($result['db_payload'])->toBeNull();
});

test('gibt Freitext zurück wenn kein JSON', function () {
    $result = $this->parser->parse('Hallo, das ist eine Antwort ohne JSON.');

    expect($result['content'])->toBe('Hallo, das ist eine Antwort ohne JSON.');
    expect($result['db_payload'])->toBeNull();
    expect($result['md_files'])->toBeEmpty();
});

test('extrahiert JSON aus Markdown-Codeblock', function () {
    $content = "Hier ist das Ergebnis:\n\n```json\n".json_encode([
        'meta' => ['phase' => 2],
        'result' => ['summary' => 'Codeblock result', 'data' => []],
        'db_payload' => ['tables' => ['p2_review_typen' => [['typ' => 'Scoping']]]],
    ])."\n```\n\nFertig.";

    $result = $this->parser->parse($content);

    expect($result['content'])->toBe('Codeblock result');
    expect($result['db_payload']['tables'])->toHaveKey('p2_review_typen');
});

test('gibt leere Struktur bei invalidem JSON zurück', function () {
    $result = $this->parser->parse('{invalid json!!!}');

    expect($result['content'])->toBe('{invalid json!!!}');
    expect($result['db_payload'])->toBeNull();
});
