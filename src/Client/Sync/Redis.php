<?php

namespace Wpt\Framework\Client\Sync;

use Wpt\Framework\Log\Log;
use Wpt\Framework\Core\Application;
use Wpt\Framework\Utility\Console;

/**
 * Class Redis
 *
 * @package Wpt\Framework\Client\Sync
 */
class Redis
{
    private $type = 'redis';
    private $pool = 'default';

    /**
     * @var string
     */
    private $use = null;

    /**
     * @var Application
     */
    private $app = null;

    /**
     * @var \Redis
     */
    private $redis = null;

    /**
     * Redis constructor.
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

        $this->pool = $pool[0];
        $this->use  = $pool[1] ?? null;

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
            Log::error('Redis [Sync] config not found : ' . $this->pool);

            return;
        }

        if ($this->use !== null) {
            $config = $config[$this->use];
        }

        try {
            $redis = new \Redis();

            // 异步redis套接字格式为: unix:/tmp/redis_6379.sock
            // 同步redis套接字格式为: /tmp/redis_6379.sock
            if (false === strpos($config['host'], 'unix:')) {
                $result = $redis->connect($config['host'], $config['port'], $config['timeout'] ?? 0);
            } else {
                $result = $redis->connect(str_replace('unix:', '', $config['host']));
            }

            if (! $result) {
                Log::error('Redis [Sync] connect fail，Pool：' . $this->pool);

                return;
            }

            if (isset($config['auth']) and $config['auth']) {
                $result = $redis->auth($config['auth']);
                if (! $result) {
                    Log::error('Redis [Sync] auth fail，Pool：' . $this->pool);

                    return;
                }
            }

            if (isset($config['select']) and $config['select']) {
                $result = $redis->select($config['select']);
                if (! $result) {
                    Log::error('Redis [Sync] select fail，Pool：' . $this->pool);

                    return;
                }
            }
        } catch (\Exception $e) {
            Log::error('Redis [Sync] connect fail：' . $e->getMessage());

            return;
        }

        $this->redis = $redis;
    }

    /**
     * 执行查询
     *
     * @param  string $name
     * @param  array  $arguments
     * @param  bool   $logSlow
     * @return mixed
     */
    public function query(string $name, array $arguments, bool $logSlow = true)
    {
        if (! $this->redis) {
            $this->connect();

            if (! $this->redis) {
                return null;
            }
        }

        $sTime  = microtime(true);
        $result = $this->redis->$name(...$arguments);

        if ($logSlow and $this->app->getConfig('enable_slow_log', false)) {
            $slowTime = $this->app->getConfig('slow_time', 0.1);
            $useTime  = microtime(true) - $sTime;

            if ($useTime > $slowTime) {
                $params  = substr(json_encode(array_values($arguments)), 1, -1);
                $message = 'Redis Sync ['
                    . number_format($useTime, 5)
                    . '] : '
                    . $name
                    . '(' . ($params ?: '...')
                    . ')';

                Log::info($message);

                if (DEBUG_MODEL) {
                    Console::debug($message, 'blue');
                }

                unset($params, $message);
            }
        }
        unset($name, $arguments);

        return $result;
    }
}
