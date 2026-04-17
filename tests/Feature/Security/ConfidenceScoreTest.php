<?php

use App\Services\ConfidenceScoreCalculator;
use App\Services\TorDetectionService;
use Illuminate\Support\Facades\Redis;

function baseInput(array $overrides = []): array
{
    return array_merge([
        '_timing' => 5000,
        '_tz' => 'Europe/Berlin',
        'email' => 'test@example.com',
    ], $overrides);
}

beforeEach(function () {
    // Mock TorDetectionService to return false by default (kein Tor)
    $this->torMock = $this->mock(TorDetectionService::class);
    $this->torMock->shouldReceive('isKnownTorOrVpnIp')->andReturn(false)->byDefault();
});

test('sauberer nutzer erhält score 0', function () {
    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(baseInput(['_captcha_solved' => '1']), '127.0.0.1', 'DE');

    expect($result['score'])->toBe(0)
        ->and($result['breakdown']['timing'])->toBe(0)
        ->and($result['breakdown']['tor'])->toBe(0)
        ->and($result['breakdown']['disposable'])->toBe(0)
        ->and($result['breakdown']['captcha'])->toBe(0);
});

test('timing unter 2000ms ergibt +50', function () {
    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(baseInput(['_timing' => 1500]), '127.0.0.1', 'DE');

    expect($result['breakdown']['timing'])->toBe(50)
        ->and($result['score'])->toBeGreaterThanOrEqual(50);
});

test('fehlendes timing-feld ergibt +50 (bot ohne js)', function () {
    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(['email' => 'test@example.com'], '127.0.0.1', 'DE');

    expect($result['breakdown']['timing'])->toBe(50);
});

test('timezone-mismatch ergibt +20', function () {
    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(baseInput(['_tz' => 'America/New_York']), '127.0.0.1', 'DE');

    expect($result['breakdown']['timezone'])->toBe(20);
});

test('bekannte tor-ip ergibt +15', function () {
    $this->torMock->shouldReceive('isKnownTorOrVpnIp')
        ->with('185.220.101.144')
        ->andReturn(true);

    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(baseInput(), '185.220.101.144', 'DE');

    expect($result['breakdown']['tor'])->toBe(15);
});

test('disposable email ergibt +40', function () {
    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(baseInput(['email' => 'test@mailinator.com']), '127.0.0.1', 'DE');

    expect($result['breakdown']['disposable'])->toBe(40);
});

test('kombinierter score summiert alle beiträge', function () {
    $this->torMock->shouldReceive('isKnownTorOrVpnIp')
        ->with('185.220.101.144')
        ->andReturn(true);

    $calc = app(ConfidenceScoreCalculator::class);
    $result = $calc->calculate(
        baseInput(['_timing' => 500, '_tz' => 'America/New_York', 'email' => 'test@mailinator.com', '_captcha_solved' => '0']),
        '185.220.101.144',
        'DE'
    );

    // 50 (timing) + 20 (tz) + 15 (tor) + 40 (disposable) + 30 (captcha) = 155
    expect($result['score'])->toBe(155);
});

test('captcha nicht gelöst erhöht score um 30', function () {
    $result = app(ConfidenceScoreCalculator::class)
        ->calculate(baseInput(['_captcha_solved' => '0']), '1.1.1.1', 'DE');
    expect($result['breakdown']['captcha'])->toBe(30);
});

test('captcha gelöst ergibt captcha-score 0', function () {
    $result = app(ConfidenceScoreCalculator::class)
        ->calculate(baseInput(['_captcha_solved' => '1']), '1.1.1.1', 'DE');
    expect($result['breakdown']['captcha'])->toBe(0);
});

test('fehlendes captcha-feld zählt als nicht gelöst', function () {
    $input = baseInput();
    unset($input['_captcha_solved']);
    $result = app(ConfidenceScoreCalculator::class)
        ->calculate($input, '1.1.1.1', 'DE');
    expect($result['breakdown']['captcha'])->toBe(30);
});
