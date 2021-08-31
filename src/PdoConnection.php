<?php

namespace mgboot\poolx;

use mgboot\bo\DotAccessData;
use mgboot\util\JsonUtils;
use PDO;
use RuntimeException;
use Throwable;

final class PdoConnection implements ConnectionInterface
{
    use ConnectionTrait;

    private PDO $pdo;

    private function __construct(array $settings, ?PoolInterface $pool = null)
    {
        if ($pool instanceof PoolInterface) {
            $this->pool = $pool;
        }

        $data = DotAccessData::fromArray($settings);

        $sb = [
            'mysql:dbname=' . $data->getString('database'),
            'host=' . $data->getString('host', '127.0.0.1'),
            'port=' . $data->getInt('port', 3306),
            'charset' . $data->getString('charset', 'utf8mb4')
        ];

        $dsn = implode(';', $sb);

        $opts = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        $username = $data->getString('username', 'root');
        $password = $data->getString('password');

        try {
            $this->pdo = new PDO($dsn, $username, $password, $opts);
        } catch (Throwable) {
            $map1 = [
                'host' => $data->getString('host', '127.0.0.1'),
                'port' => $data->getInt('port', 3306),
                'username' => $username,
                'password' => $password,
                'datasource' => $data->getString('database')
            ];

            throw new RuntimeException('fail to create pdo connection, settings: ' . JsonUtils::toJson($map1));
        }
    }

    private function __clone(): void
    {
    }

    public static function create(array $settings, ?PoolInterface $pool = null): self
    {
        return new self($settings, $pool);
    }

    public function getRealConnection(): PDO
    {
        return $this->pdo;
    }
}
