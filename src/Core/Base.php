<?php

namespace Wpt\Framework\Core;

use Wpt\Framework\Database\Model;
use Wpt\Framework\Server\Server;
use Swoole\Process as SwooleProcess;

/**
 * Class Base
 *
 * @package Wpt\Framework\Core
 */
abstract class Base
{
    /**
     * 开始时间，用于记录请求执行时间
     *
     * @var int|mixed
     */
    protected $startTime = 0;

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Server
     */
    protected $server;

    /**
     * Base constructor.
     *
     * @param Application $app
     * @param Server      $server
     */
    public function __construct(Application $app, Server $server = null)
    {
        $this->app = $app;
        $this->server = $server;

        // 开始运行时间
        $this->startTime = microtime(true);
    }

    /**
     * 获取 Server
     *
     * @return Server
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * 获取 Swoole Server
     *
     * @return \Swoole\Server
     */
    public function getSwooleServer()
    {
        return $this->server instanceof Server ? $this->server->getServer() : null;
    }

    /**
     * 获取 Model 实例
     *
     * @param string $name
     * @param string $use
     * @param string $readWrite
     * @return Model
     */
    public function model(string $name, string $use = null, string $readWrite = 'auto')
    {
        $name = strpos($name, '/') !== false
            ? join('\\', array_map('ucfirst', explode('/', $name)))
            : ucfirst($name);

        $model = "\\App\\Model\\" . ucfirst($name);

        /**
         * @var Model $model
         */
        $model = $this->app->make($model);

        if ($use) {
            $model->use($use);
        }

        if ($readWrite === 'master') {
            $model->master();
        } elseif ($readWrite === 'slave') {
            $model->slave();
        }

        return $model;
    }

    /**
     * 获取 Redis 实例
     *
     * @param string $pool
     * @param string $cacheKey
     * @return \Redis|\Wpt\Framework\Client\Redis
     */
    public function redis(string $pool = null, string $cacheKey = null)
    {
        return $this->app->get('redis', $pool, $cacheKey);
    }

    /**
     * 事物方法
     *
     * @param  string $action
     * @param  null   $uuid
     * @return \Generator
     * @throws \Exception
     */
    protected function transaction($action = 'begin', $uuid = null)
    {
        $action = strtolower($action);

        if ($action != 'begin' and ! $uuid) {
            throw new \Exception('rollback,commit 方法 uuid 不能为空');
        }

        switch ($action) {
            case 'begin' :
                return yield $this->app->get('model')->begin();
            case 'commit' :
                return yield $this->app->get('model')->commit($uuid);
            case 'rollback' :
                return yield $this->app->get('model')->rollback($uuid);
        }

        throw new \Exception('不支持的事物方法');
    }

    /**
     * 投递一个 Task
     *
     * @param array $arguments
     * @return $this
     */
    protected function task(array $arguments = [])
    {
        $this->getSwooleServer()->task($arguments);

        return $this;
    }

    /**
     * 并行任务处理器
     *
     * @return \Wpt\Framework\Client\Multi
     */
    protected function multi()
    {
        return $this->app->get('multi');
    }

    /**
     * 析构函数，用于 Command, Process
     *
     * Base destructor.
     */
    public function __destruct()
    {
        if ($this->server and $this->server instanceof SwooleProcess) {
            $this->server->exit();
        }
    }
}
