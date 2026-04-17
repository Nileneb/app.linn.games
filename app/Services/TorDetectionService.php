<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class TorDetectionService
{
    private const REDIS_KEY = 'security:tor_nodes';

    public function isKnownTorOrVpnIp(string $ip): bool
    {
        try {
            return (bool) Redis::sismember(self::REDIS_KEY, $ip);
        } catch (\Throwable) {
            return false;
        }
    }
}
