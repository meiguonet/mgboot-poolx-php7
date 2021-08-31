<?php

namespace mgboot\poolx;

use mgboot\AppConf;
use mgboot\bo\DotAccessData;
use mgboot\util\JsonUtils;
use PDO;
use RuntimeException;
use Throwable;

final class PdoConnection implements ConnectionInterface
{
    use ConnectionTrait;

    /**
     * @var PDO
     */
    private $pdo;

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

        $dbname = $data->getString('database');

        if (is_string($cliSettings['database']) && $cliSettings['database'] !== '') {
            $dbname = $cliSettings['database'];
        }

        $host = $data->getString('host');

        if (is_string($cliSettings['host']) && $cliSettings['host'] !== '') {
            $host = $cliSettings['host'];
        }

        if (empty($host)) {
            $host = '127.0.0.1';
        }

        $port = $data->getInt('port');

        if (is_string($cliSettings['port']) && $cliSettings['port'] > 0) {
            $port = $cliSettings['port'];
        }

        if ($port < 1) {
            $port = 3306;
        }

        $charset = $data->getString('charset');

        if (empty($charset)) {
            $charset = 'utf8mb4';
        }

        $username = $data->getString('username');

        if (is_string($cliSettings['username']) && $cliSettings['username'] !== '') {
            $username = $cliSettings['username'];
        }

        if (empty($username)) {
            $username = 'root';
        }

        $password = $data->getString('password');

        if (is_string($cliSettings['password']) && $cliSettings['password'] !== '') {
            $password = $cliSettings['password'];
        }

        $sb = [
            "mysql:dbname=$dbname",
            "host=$host",
            "port=$port",
            "charset=$charset"
        ];

        $dsn = implode(';', $sb);

        $opts = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        try {
            $this->pdo = new PDO($dsn, $username, $password, $opts);
        } catch (Throwable $ex) {
            $map1 = compact('host', 'port', 'username', 'password', 'dbname');
            throw new RuntimeException('fail to create pdo connection, settings: ' . JsonUtils::toJson($map1));
        }
    }

    private function __clone()
    {
    }

    public static function create(array $settings, ?PoolInterface $pool = null): PdoConnection
    {
        return new self($settings, $pool);
    }

    public function getRealConnection(): PDO
    {
        return $this->pdo;
    }
}
