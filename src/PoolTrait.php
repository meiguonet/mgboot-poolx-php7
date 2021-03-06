<?php /** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace mgboot\poolx;

use mgboot\bo\DotAccessData;
use mgboot\Cast;
use mgboot\constant\Regexp;
use mgboot\swoole\Swoole;
use mgboot\util\ArrayUtils;
use mgboot\util\StringUtils;
use RuntimeException;
use Throwable;

trait PoolTrait
{
    /**
     * @var int
     */
    private $minActive = 10;

    /**
     * @var int
     */
    private $maxActive = 10;

    /**
     * @var int
     */
    private $currentActive = 0;

    /**
     * @var float
     */
    private $takeTimeout = 3.0;

    /**
     * @var int
     */
    private $maxIdle = 1800;

    /**
     * @var int
     */
    private $idleCheckInternal = 10;

    /**
     * @var mixed
     */
    private $connChan = null;

    /**
     * @var ConnectionBuilderInterface|null
     */
    private $connectionBuilder = null;

    public function withConnectionBuilder(ConnectionBuilderInterface $builder): void
    {
        $this->connectionBuilder = $builder;
    }

    public function run(): void
    {
        $builder = $this->connectionBuilder;

        if (!($builder instanceof ConnectionBuilderInterface)) {
            return;
        }

        $ch = new \Swoole\Coroutine\Channel($this->maxActive);

        for ($i = 1; $i <= $this->minActive; $i++) {
            try {
                $conn = $builder->buildConnection();
                $ch->push($conn);
                $this->currentActive++;
            } catch (Throwable $ex) {
            }
        }

        $this->connChan = $ch;
        $this->runIdleChecker();
    }

    /**
     * @param int|float|null $timeout
     * @return ConnectionInterface
     */
    public function take($timeout = null): ConnectionInterface
    {
        $ex1 = new RuntimeException('fail to take connection from connection pool: ' . get_class($this));
        $ch = $this->connChan;

        if (!($ch instanceof \Swoole\Coroutine\Channel)) {
            throw $ex1;
        }

        $conn = $ch->pop(0.01);

        if ($conn instanceof ConnectionInterface) {
            $conn->updateLastUsedAt();
            return $conn;
        }

        $builder = $this->connectionBuilder;

        if ($this->currentActive < $this->maxActive && $builder instanceof ConnectionBuilderInterface) {
            try {
                $conn = $builder->buildConnection();
            } catch (Throwable $ex) {
                $conn = null;
            }

            if ($conn instanceof ConnectionInterface) {
                $this->currentActive++;
                $conn->updateLastUsedAt();
                return $conn;
            }
        }

        $timeout = Cast::toFloat($timeout);

        if ($timeout < 1.0) {
            $timeout = $this->takeTimeout;
        }

        $conn = $ch->pop($timeout);

        if ($conn instanceof ConnectionInterface) {
            $conn->updateLastUsedAt();
            return $conn;
        }

        throw $ex1;
    }

    public function release(ConnectionInterface $conn): void
    {
        $ch = $this->connChan;

        if ($ch instanceof \Swoole\Coroutine\Channel) {
            $ch->push($conn);
        }
    }

    /**
     * @param int|string|null $timeout
     */
    public function destroy($timeout = null): void
    {
        $ch = $this->connChan;

        if (!($ch instanceof \Swoole\Coroutine\Channel)) {
            return;
        }

        $_timeout = 5;

        if (is_int($timeout) && $timeout > 0) {
            $_timeout = $timeout;
        } else if (is_string($timeout) && $timeout !== '') {
            $timeout = Cast::toDuration($timeout);

            if ($timeout > 0) {
                $_timeout = $timeout;
            }
        }

        $ts = time();

        while (true) {
            if (time() - $ts > $_timeout) {
                break;
            }

            for ($i = 1; $i <= $this->maxActive; $i++) {
                $conn = $ch->pop(0.01);

                if ($conn instanceof ConnectionInterface) {
                    $conn->close();
                }
            }
        }
    }

    private function runIdleChecker(): void
    {
        $ch = $this->connChan;

        if (!($ch instanceof \Swoole\Coroutine\Channel)) {
            return;
        }

        $builder = $this->connectionBuilder;

        Swoole::timerTick($this->idleCheckInternal * 1000, function () use ($ch, $builder) {
            $now = time();
            $connections = [];

            while (!$ch->isEmpty()) {
                $conn = $ch->pop(0.01);

                if (!($conn instanceof ConnectionInterface)) {
                    continue;
                }

                if ($now - $conn->getLastUsedAt() > $this->maxIdle) {
                    try {
                        $conn->close();
                    } catch (Throwable $ex) {
                    }

                    $this->currentActive--;
                    continue;
                }

                $connections[] = $conn;
            }

            foreach ($connections as $conn) {
                $ch->push($conn);
            }

            if ($this->currentActive < $this->minActive && $builder instanceof ConnectionBuilderInterface) {
                $n1 = $this->minActive - $this->currentActive;

                for ($i = 1; $i <= $n1; $i++) {
                    try {
                        $conn = $builder->buildConnection();
                        $ch->push($conn);
                        $this->currentActive++;
                    } catch (Throwable $ex) {
                    }
                }
            }
        });
    }

    private function init(array $settings): void
    {
        $settings = $this->handleSettings($settings);
        $data = DotAccessData::fromArray($settings);
        $minActive = $data->getInt('minActive', 10);
        $maxActive = $data->getInt('maxActive', $minActive);

        if ($maxActive < $minActive) {
            $maxActive = $minActive;
        }

        $takeTimeout = $data->getFloat('takeTimeout', 3.0);

        if ($takeTimeout < 1.0) {
            $takeTimeout = 1.0;
        }

        $maxIdle = 1800;

        if (is_int($settings['maxIdle']) && $settings['maxIdle'] > 0) {
            $maxIdle = $settings['maxIdle'];
        } else if (is_string($settings['maxIdle']) && $settings['maxIdle'] !== '') {
            $n1 = StringUtils::toDuration($settings['maxIdle']);

            if ($n1 > 0) {
                $maxIdle = $n1;
            }
        }

        $idleCheckInternal = 10;

        if (is_int($settings['idleCheckInternal']) && $settings['idleCheckInternal'] > 0) {
            $idleCheckInternal = $settings['idleCheckInternal'];
        } else if (is_string($settings['idleCheckInternal']) && $settings['idleCheckInternal'] !== '') {
            $n1 = StringUtils::toDuration($settings['idleCheckInternal']);

            if ($n1 > 0) {
                $idleCheckInternal = $n1;
            }
        }

        $this->minActive = $minActive;
        $this->maxActive = $maxActive;
        $this->takeTimeout = $takeTimeout;
        $this->maxIdle = $maxIdle;
        $this->idleCheckInternal = $idleCheckInternal;
    }

    private function handleSettings(array $settings): array
    {
        if (!ArrayUtils::isAssocArray($settings)) {
            return [];
        }

        foreach ($settings as $key => $val) {
            $newKey = strtr($key, ['-' => ' ', '_' => ' ']);
            $newKey = preg_replace(Regexp::SPACE_SEP, ' ', $newKey);
            $newKey = str_replace(' ', '', ucwords($newKey));
            $newKey = lcfirst($newKey);

            if ($newKey === $key) {
                continue;
            }

            $settings[$newKey] = $val;
            unset($settings[$key]);
        }

        return $settings;
    }
}
