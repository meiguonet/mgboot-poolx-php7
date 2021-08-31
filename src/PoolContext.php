<?php

namespace mgboot\poolx;

use mgboot\swoole\Swoole;
use Throwable;

final class PoolContext
{
    const POOL_TYPE_DB = 1;
    const POOL_TYPE_REDIS = 2;
    private static array $pools = [];

    private function __construct()
    {
    }

    private function __clone(): void
    {
    }

    public static function addPool(int $poolType, PoolInterface $pool, ?int $workerId = null): void
    {
        $key = self::getPoolKey($poolType, $workerId);
        self::$pools[$key] = $pool;
    }

    public static function getPool(int $poolType, ?int $workerId = null): ?PoolInterface
    {
        if (!is_int($workerId)) {
            $workerId = Swoole::getWorkerId();
        }

        $key = self::getPoolKey($poolType, $workerId);
        $pool = self::$pools[$key];
        return $pool instanceof PoolInterface ? $pool : null;
    }

    public static function getConnection(int $poolType, float|int|null $timeout = null): ?ConnectionInterface
    {
        $pool = self::getPool($poolType);

        if (!($pool instanceof PoolInterface)) {
            return null;
        }

        try {
            $conn = $pool->take($timeout);
        } catch (Throwable) {
            $conn = null;
        }

        return $conn instanceof ConnectionInterface ? $conn : null;
    }

    private static function getPoolKey(int $poolType, ?int $workerId = null): string
    {
        $s1 = match ($poolType) {
            self::POOL_TYPE_DB => 'db-',
            self::POOL_TYPE_REDIS => 'redis-',
            default => 'ntp-'
        };

        return is_int($workerId) && $workerId >= 0 ? "{$s1}worker$workerId" : "{$s1}noworker";
    }
}
