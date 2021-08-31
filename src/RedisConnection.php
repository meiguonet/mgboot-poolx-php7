<?php

namespace mgboot\poolx;

use mgboot\bo\DotAccessData;
use mgboot\util\JsonUtils;
use Redis;
use RuntimeException;
use Throwable;

final class RedisConnection implements ConnectionInterface
{
    use ConnectionTrait;

    private Redis $redis;

    private function __construct(array $settings, ?PoolInterface $pool = null)
    {
        if ($pool instanceof PoolInterface) {
            $this->pool = $pool;
        }

        $data = DotAccessData::fromArray($settings);
        $host = $data->getString('host');

        if ($host === '') {
            $host = '127.0.0.1';
        }

        $port = $data->getInt('port');

        if ($port < 1) {
            $port = 6379;
        }

        $database = $data->getInt('database');
        $password = $data->getString('password');
        $readTimeout = $data->getDuration('read-timeout');

        if ($readTimeout < 1) {
            $readTimeout = 5;
        }

        $map1 = compact('host', 'port', 'password');

        if ($database > 0) {
            $map1['database'] = $database;
        }

        $ex1 = new RuntimeException('fail to create redis connection, settings: ' . JsonUtils::toJson($map1));

        try {
            $redis = new Redis();

            if (!$redis->connect($host, $port, 1.0, null, 0, $readTimeout)) {
                throw $ex1;
            }

            if ($password !== '' && !$redis->auth($password)) {
                throw $ex1;
            }

            if ($database > 0 && !$redis->select($database)) {
                throw $ex1;
            }

            $this->redis = $redis;
        } catch (Throwable) {
            throw $ex1;
        }
    }

    private function __clone(): void
    {
    }

    public static function create(array $settings, ?PoolInterface $pool = null): self
    {
        return new self($settings, $pool);
    }

    public function getRealConnection(): Redis
    {
        return $this->redis;
    }
}
