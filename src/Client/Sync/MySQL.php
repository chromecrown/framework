<?php

namespace Flower\Client\Sync;

use Flower\Core\Application;
use Flower\Log\Log;
use Flower\Utility\Console;

/**
 * Class MySQL
 *
 * @package Flower\Client\Sync
 */
class MySQL
{
    /**
     * @var string
     */
    private $type = 'mysql';

    /**
     * @var string
     */
    private $pool = 'default';

    /**
     * @var mixed
     */
    private $slave = null;

    /**
     * @var Application
     */
    private $app = null;

    /**
     * @var \PDO
     */
    private $pdo = null;

    /**
     * MySQL constructor.
     *
     * @param Application $app
     * @param string      $pool
     */
    public function __construct(Application $app, string $pool = 'default')
    {
        $this->app = $app;

        $this->use($pool);
    }

    /**
     * 设置使用连接资源
     *
     * @param  string $pool
     * @return $this
     */
    public function use(string $pool)
    {
        $pool = explode('_', $pool);

        $this->pool  = $pool[0];
        $this->slave = $pool[1] ?? null;

        return $this;
    }

    /**
     * 连接资源
     *
     * @throws \Exception
     */
    private function connect()
    {
        $config = $this->app['config']->get($this->type . '/' . $this->pool, null);
        if (! $config) {
            throw new \Exception('MySQL 配置不存在：' . $this->pool);
        }

        if ($this->slave == null) {
            $config = $config['master'];
        } else {
            $config = $config['slave'][$this->slave];
        }

        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

        try {
            $pdo = new \PDO($dsn, $config['user'], $config['password']);
            $pdo->exec("SET NAMES '{$config['charset']}'");
        } catch (\Exception $e) {
            Log::error('MySQL [Sync] connect fail：' . $e->getMessage(), null);

            return;
        }

        $this->pdo = $pdo;
    }

    /**
     * 执行查询
     *
     * @param  string $sql
     * @param  bool   $logSlow
     * @return array
     * @throws \Exception
     */
    public function query(string $sql, bool $logSlow = true)
    {
        $data = [
            'result'        => null,
            'insert_id'     => null,
            'affected_rows' => null,
        ];

        if (! $sql) {
            Log::error('MySQL [Sync] sql is empty.');

            return $data;
        }

        if (! $this->pdo) {
            $this->connect();

            if (! $this->pdo) {
                return $data;
            }
        }

        $sTime  = microtime(true);
        $prefix = strtolower(substr($sql, 0, 6));
        switch ($prefix) {
            case 'select' :
                $result = $this->pdo->query($sql);
                if ($result instanceof \PDOStatement) {
                    $data['result'] = $result->fetchAll(\PDO::FETCH_ASSOC);
                    unset($result);
                }
                break;

            case 'insert' :
                $result = $this->pdo->exec($sql);
                if ($result !== false) {
                    $data['insert_id'] = $this->pdo->lastInsertId();
                }
                break;

            // update & delete
            default :
                $result = $this->pdo->exec($sql);
                if ($result !== false) {
                    $data['affected_rows'] = $result;
                }
                break;
        }

        if ($logSlow and $this->app->getConfig('enable_slow_log', false)) {
            $slowTime = $this->app->getConfig('slow_time', 0.1);
            $useTime = microtime(true) - $sTime;

            if ($useTime > $slowTime) {
                $message = 'MySQL Sync [' . number_format($useTime, 5) . '] : ' . $sql;

                Log::info($message);
                Console::debug($message, 'blue');
            }
        }
        unset($sql, $result);

        return $data;
    }
}
