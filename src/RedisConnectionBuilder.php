<?php

namespace mgboot\poolx;

final class RedisConnectionBuilder implements ConnectionBuilderInterface
{
    /**
     * @var array
     */
    private $settings;

    /**
     * @var PoolInterface|null
     */
    private $pool;

    private function __construct(array $settings, ?PoolInterface $pool  = null)
    {
        $this->settings = $settings;
        $this->pool = $pool;
    }

    private function __clone()
    {
    }

    public static function create(array $settings, ?PoolInterface $pool  = null): RedisConnectionBuilder
    {
        return new self($settings, $pool);
    }

    public function buildConnection(): ConnectionInterface
    {
        return RedisConnection::create($this->settings, $this->pool);
    }
}
