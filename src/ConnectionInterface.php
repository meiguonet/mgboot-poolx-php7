<?php

namespace mgboot\poolx;

use Throwable;

interface ConnectionInterface
{
    public function fromPool(): bool;

    public function inTranstionMode(?bool $flag = null): bool;

    public function getRealConnection(): mixed;

    public function updateLastUsedAt(): void;

    public function getLastUsedAt(): int;

    public function close(): void;

    public function free(?Throwable $ex = null): void;
}
