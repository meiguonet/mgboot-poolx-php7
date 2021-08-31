<?php

namespace mgboot\poolx;

use mgboot\AppConf;
use mgboot\bo\DotAccessData;
use mgboot\util\JsonUtils;
use Redis;
use RuntimeException;
use Throwable;

final class RedisConnection implements ConnectionInterface
{
    use ConnectionTrait;

    /**
     * @var Redis
     */
    private $redis;

    private function __construct(array $settings, ?PoolInterface $pool = null)
    {
        if ($pool instanceof PoolInterface) {
            $this->pool = $pool;
        }

        $data = DotAccessData::fromArray($settings);

        if (AppConf::getEnv() === 'dev' && stripos(php_sapi_name(), 'cli') !== false) {
            $cliSettings = $data->getAssocArray('cli-mode');
        } else {
            $cliSettings = [];
        }

        $host = $data->getString('host');

        if (is_string($cliSettings['host']) && $cliSettings['host'] !== '') {
            $host = $cliSettings['host'];
        }

        if (empty($host)) {
            $host = '127.0.0.1';
        }

        $port = $data->getInt('port');

        if (is_int($cliSettings['port']) && $cliSettings['port'] > 0) {
            $port = $cliSettings['port'];
        }

        if ($port < 1) {
            $port = 6379;
        }

        $password = $data->getString('password');

        if (is_string($cliSettings['password']) && $cliSettings['password'] !== '') {
            $password = $cliSettings['password'];
        }

        $database = $data->getInt('database');

        if (is_int($cliSettings['database']) && $cliSettings['database'] >= 0) {
            $database = $cliSettings['database'];
        }

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
        } catch (Throwable $ex) {
            throw $ex1;
        }
    }

    private function __clone()
    {
    }

    public static function create(array $settings, ?PoolInterface $pool = null): RedisConnection
    {
        return new self($settings, $pool);
    }

    public function getRealConnection(): Redis
    {
        return $this->redis;
    }
}
