<?php

namespace Flower\Client;

use Flower\Server\Server;
use Flower\Core\Application;

/**
 * Class Redis
 *
 * @package Flower\Client
 */
class Redis
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @var Server
     */
    private $server;

    /**
     * @var string
     */
    private $pool = 'default';

    /**
     * Redis constructor.
     *
     * @param Application $app
     * @param Server      $server
     * @param string|null $pool
     * @param string|null $cacheKey
     */
    public function __construct(
        Application $app,
        Server $server,
        string $pool = null,
        string $cacheKey = null
    ) {
        $this->app = $app;
        $this->server = $server;

        $pool and $this->use($pool, $cacheKey);
    }

    /**
     * @param string      $pool
     * @param string|null $cacheKey
     * @return $this
     */
    public function use (string $pool, string $cacheKey = null)
    {
        if ($cacheKey) {
            $group = $this->app->getConfig('_redis.' . $pool);
            $index = crc32($cacheKey) % count($group);

            // 不存在则取第一个
            $pool = $group[$index] ?? current($group);
        }

        $this->pool = $pool;

        return $this;
    }

    /**
     * @param null|callable $callback
     * @param string        $method
     * @param array         $arguments
     * @param bool          $async
     * @param bool          $logSlow
     */
    public function call($callback, string $method, array $arguments, bool $async = true, bool $logSlow = true)
    {
        if (! $async or ($this->server->getServer()->taskworker ?? false)) {
            $result = $this->syncQuery($method, $arguments, $logSlow);

            $callback and $callback($result);
        } else {
            $this->app['pool.manager']
                ->get('redis', $this->pool)
                ->call($callback ?: function () {}, $method, $arguments, $logSlow);
        }
    }

    /**
     * @param string $method
     * @param array  $arguments
     * @param bool   $logSlow
     * @return mixed
     */
    private function syncQuery(string $method, array $arguments, bool $logSlow)
    {
        return $this->app->get('client.redis.sync', $this->pool)->query($method, $arguments, $logSlow);
    }

    /**
     * @param string $name
     * @param array  $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if ($this->server->getServer()->taskworker ?? false) {
            return $this->syncQuery($name, $arguments, true);
        }

        return $this->app['pool.manager']->get('redis', $this->pool)->query($name, $arguments);
    }
}
