<?php

use App\Services\PhaseScoreService;
use App\Models\PhaseAgentResult;

test('score wird auf 0-100 normalisiert und level abgeleitet', function () {
    $result = Mockery::mock(PhaseAgentResult::class);
    $result->shouldReceive('update')
        ->once()
        ->with(['result_data' => ['qualitaets_bewertung' => [
            'score' => 85,
            'level' => 'sehr_gut',
        ]]]);

    $parsed = ['meta' => ['qualitaets_bewertung' => ['score' => 85]]];

    (new PhaseScoreService)->calculateAndPersistP1Score($result, $parsed);
});

test('score 80 ergibt level sehr_gut', function () {
    $result = Mockery::mock(PhaseAgentResult::class);
    $result->shouldReceive('update')
        ->once()
        ->withArgs(fn ($data) => $data['result_data']['qualitaets_bewertung']['level'] === 'sehr_gut');

    (new PhaseScoreService)->calculateAndPersistP1Score($result, ['meta' => ['qualitaets_bewertung' => ['score' => 80]]]);
});

test('score 60 ergibt level gut', function () {
    $result = Mockery::mock(PhaseAgentResult::class);
    $result->shouldReceive('update')
        ->once()
        ->withArgs(fn ($data) => $data['result_data']['qualitaets_bewertung']['level'] === 'gut');

    (new PhaseScoreService)->calculateAndPersistP1Score($result, ['meta' => ['qualitaets_bewertung' => ['score' => 60]]]);
});

test('score 40 ergibt level befriedigend', function () {
    $result = Mockery::mock(PhaseAgentResult::class);
    $result->shouldReceive('update')
        ->once()
        ->withArgs(fn ($data) => $data['result_data']['qualitaets_bewertung']['level'] === 'befriedigend');

    (new PhaseScoreService)->calculateAndPersistP1Score($result, ['meta' => ['qualitaets_bewertung' => ['score' => 40]]]);
});

test('score unter 40 ergibt level schwach', function () {
    $result = Mockery::mock(PhaseAgentResult::class);
    $result->shouldReceive('update')
        ->once()
        ->withArgs(fn ($data) => $data['result_data']['qualitaets_bewertung']['level'] === 'schwach');

    (new PhaseScoreService)->calculateAndPersistP1Score($result, ['meta' => ['qualitaets_bewertung' => ['score' => 39]]]);
});

test('überhöhter score wird auf 100 geclampt', function () {
    $result = Mockery::mock(PhaseAgentResult::class);
    $result->shouldReceive('update')
        ->once()
        ->withArgs(fn ($data) => $data['result_data']['qualitaets_bewertung']['score'] === 100);

    (new PhaseScoreService)->calculateAndPersistP1Score($result, ['meta' => ['qualitaets_bewertung' => ['score' => 150]]]);
});

test('negativer score wird auf 0 geclampt und ist schwach', function () {
    $result = Mockery::mock(PhaseAgentResult::class);
    $result->shouldReceive('update')
        ->once()
        ->withArgs(fn ($data) => $data['result_data']['qualitaets_bewertung']['score'] === 0
            && $data['result_data']['qualitaets_bewertung']['level'] === 'schwach');

    (new PhaseScoreService)->calculateAndPersistP1Score($result, ['meta' => ['qualitaets_bewertung' => ['score' => -10]]]);
});

test('kein update wenn bewertung fehlt', function () {
    $result = Mockery::mock(PhaseAgentResult::class);
    $result->shouldNotReceive('update');

    (new PhaseScoreService)->calculateAndPersistP1Score($result, ['meta' => []]);
});

test('kein update wenn score key fehlt', function () {
    $result = Mockery::mock(PhaseAgentResult::class);
    $result->shouldNotReceive('update');

    (new PhaseScoreService)->calculateAndPersistP1Score($result, ['meta' => ['qualitaets_bewertung' => ['kommentar' => 'gut']]]);
});
