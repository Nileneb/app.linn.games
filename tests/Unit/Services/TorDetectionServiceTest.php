<?php

use App\Services\TorDetectionService;
use Illuminate\Support\Facades\Redis;

test('isKnownTorOrVpnIp gibt false zurück wenn Redis-Set leer', function () {
    Redis::shouldReceive('sismember')
        ->with('security:tor_nodes', '1.2.3.4')
        ->andReturn(0);

    $service = app(TorDetectionService::class);
    expect($service->isKnownTorOrVpnIp('1.2.3.4'))->toBeFalse();
});

test('isKnownTorOrVpnIp erkennt bekannte Tor-IP', function () {
    Redis::shouldReceive('sismember')
        ->with('security:tor_nodes', '185.220.101.144')
        ->once()
        ->andReturn(1);

    $service = app(TorDetectionService::class);
    expect($service->isKnownTorOrVpnIp('185.220.101.144'))->toBeTrue();
});

test('isKnownTorOrVpnIp gibt false zurück bei Redis-Fehler', function () {
    Redis::shouldReceive('sismember')
        ->andThrow(new \Exception('Redis connection error'));

    $service = app(TorDetectionService::class);
    expect($service->isKnownTorOrVpnIp('185.220.101.144'))->toBeFalse();
});
