<?php /** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace mgboot\poolx;

use PDO;
use Redis;
use Throwable;

trait ConnectionTrait
{
    /**
     * @var PoolInterface|null
     */
    private $pool = null;

    /**
     * @var bool
     */
    private $transationFlag = false;

    /**
     * @var int
     */
    private $lastUsedAt = 0;

    public function fromPool(): bool
    {
        return $this->pool instanceof PoolInterface;
    }

    public function inTranstionMode(?bool $flag = null): bool
    {
        if (is_bool($flag)) {
            $this->transationFlag = $flag;
            return $flag;
        }

        return $this->transationFlag;
    }

    public function updateLastUsedAt(): void
    {
        $this->lastUsedAt = time();
    }

    public function getLastUsedAt(): int
    {
        return $this->lastUsedAt;
    }

    public function close(): void
    {
        if (!method_exists($this, 'getRealConnection')) {
            return;
        }

        $conn = $this->getRealConnection();

        if (!is_object($conn)) {
            return;
        }

        if ($conn instanceof PDO) {
            unset($conn);
            return;
        }

        if ($conn instanceof Redis) {
            $conn->close();
            return;
        }

        if ($conn instanceof \Swoole\Coroutine\Client) {
            $conn->close();
            return;
        }

        if (!method_exists($conn, 'close')) {
            return;
        }

        $conn->close();
    }

    public function free(?Throwable $ex = null): void
    {
        if ($ex instanceof Throwable && str_contains($ex->getMessage(), 'gone away')) {
            return;
        }

        if ($this->transationFlag) {
            return;
        }

        $pool = $this->pool;

        if ($pool instanceof PoolInterface) {
            $pool->release($this);
        }
    }
}
