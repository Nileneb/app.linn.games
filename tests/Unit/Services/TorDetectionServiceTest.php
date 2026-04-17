<?php

namespace Tests\Unit\Services;

use App\Services\TorDetectionService;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\TestCase;

class TorDetectionServiceTest extends TestCase
{
    public function test_isKnownTorOrVpnIp_gibt_false_zurück_wenn_redis_set_leer(): void
    {
        $redisMock = \Mockery::mock('alias:Illuminate\Support\Facades\Redis');
        $redisMock->shouldReceive('sismember')
            ->with('security:tor_nodes', '1.2.3.4')
            ->andReturn(0);

        $service = new TorDetectionService();
        $this->assertFalse($service->isKnownTorOrVpnIp('1.2.3.4'));
    }

    public function test_isKnownTorOrVpnIp_erkennt_bekannte_tor_ip(): void
    {
        $redisMock = \Mockery::mock('alias:Illuminate\Support\Facades\Redis');
        $redisMock->shouldReceive('sismember')
            ->with('security:tor_nodes', '185.220.101.144')
            ->andReturn(1);
        $redisMock->shouldReceive('sismember')
            ->with('security:tor_nodes', '1.2.3.4')
            ->andReturn(0);

        $service = new TorDetectionService();
        $this->assertTrue($service->isKnownTorOrVpnIp('185.220.101.144'));
        $this->assertFalse($service->isKnownTorOrVpnIp('1.2.3.4'));
    }

    public function test_isKnownTorOrVpnIp_gibt_false_zurück_bei_redis_fehler(): void
    {
        $redisMock = \Mockery::mock('alias:Illuminate\Support\Facades\Redis');
        $redisMock->shouldReceive('sismember')
            ->andThrow(new \Exception('Redis error'));

        $service = new TorDetectionService();
        $this->assertFalse($service->isKnownTorOrVpnIp('185.220.101.144'));
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
