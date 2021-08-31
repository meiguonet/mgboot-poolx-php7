<?php

namespace mgboot\poolx;

final class RedisPool implements PoolInterface
{
    use PoolTrait;

    private function __construct(array $settings)
    {
        $this->init($settings);
    }

    private function __clone()
    {
    }

    public static function create(array $settings): RedisPool
    {
        return new self($settings);
    }
}
