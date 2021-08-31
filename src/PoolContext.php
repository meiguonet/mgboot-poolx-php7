<?php

namespace mgboot\poolx;

use mgboot\swoole\Swoole;
use Throwable;

final class PoolContext
{
    const POOL_TYPE_DB = 1;
    const POOL_TYPE_REDIS = 2;

    /**
     * @var array
     */
    private static $pools = [];

    private function __construct()
    {
    }

    private function __clone()
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

    /**
     * @param int $poolType
     * @param float|int|null $timeout
     * @return ConnectionInterface|null
     */
    public static function getConnection(int $poolType, $timeout = null): ?ConnectionInterface
    {
        $pool = self::getPool($poolType);

        if (!($pool instanceof PoolInterface)) {
            return null;
        }

        try {
            $conn = $pool->take($timeout);
        } catch (Throwable $ex) {
            $conn = null;
        }

        return $conn instanceof ConnectionInterface ? $conn : null;
    }

    private static function getPoolKey(int $poolType, ?int $workerId = null): string
    {
        switch ($poolType) {
            case self::POOL_TYPE_DB:
                $s1 = 'db-';
                break;
            case self::POOL_TYPE_REDIS:
                $s1 = 'redis-';
                break;
            default:
                $s1 = 'ntp-';
                break;
        }

        return is_int($workerId) && $workerId >= 0 ? "{$s1}worker$workerId" : "{$s1}noworker";
    }
}
