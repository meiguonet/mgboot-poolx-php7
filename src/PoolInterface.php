<?php

namespace mgboot\poolx;

interface PoolInterface
{
    public function withConnectionBuilder(ConnectionInterface $builder): void;

    public function run(): void;

    public function take(int|float|null $timeout = null): ConnectionInterface;

    public function release(ConnectionInterface $conn): void;

    public function destroy(int|string|null $timeout = null): void;
}
