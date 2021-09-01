<?php

namespace mgboot\poolx;

interface PoolInterface
{
    public function withConnectionBuilder(ConnectionBuilderInterface $builder): void;

    public function run(): void;

    /**
     * @param int|float|null $timeout
     * @return ConnectionInterface
     */
    public function take($timeout = null): ConnectionInterface;

    public function release(ConnectionInterface $conn): void;

    /**
     * @param int|string|null $timeout
     */
    public function destroy($timeout = null): void;
}
