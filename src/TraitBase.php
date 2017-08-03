<?php

namespace Weipaitang\Framework;

use Swoole\Server;
use Weipaitang\Container\ContainerInterface;
use Weipaitang\Database\Model;

/**
 * Trait TraitBase
 * @package Weipaitang\Framework
 */
trait TraitBase
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Server|\Swoole\WebSocket\Server
     */
    protected $server;

    /**
     * Base constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param Server $server
     * @return $this
     */
    public function withServer($server)
    {
        $this->server = $server;

        return $this;
    }

    /**
     * @return Server
     */
    public function getServer()
    {
        return $this->server ?: $this->container->get('server')->getServer();
    }

    /**
     * @param string $name
     * @param string $connect
     * @param string $readWrite
     * @return Model
     */
    public function model(string $name, string $connect = null, string $readWrite = 'auto')
    {
        $name = strpos($name, '/') !== false
            ? join('\\', array_map('ucfirst', explode('/', $name)))
            : ucfirst($name);

        $model = "\\App\\Model\\" . ucfirst($name);

        /**
         * @var Model $model
         */
        $model = $this->container->make($model);

        if ($connect) {
            $model->withConnect($connect);
        }

        $model->withReadWrite($readWrite);

        return $model;
    }

    /**
     * @param string $pool
     * @return \Redis|\Weipaitang\Client\Async\Pool\RedisPool
     */
    public function redis(string $pool = null)
    {
        return $this->container->get('pool')->select($pool);
    }

    /**
     * @param  string $action
     * @param  null   $uuid
     * @return \Generator
     * @throws \Exception
     */
    protected function transaction($action = 'begin', $uuid = null)
    {
//        $action = strtolower($action);
//
//        if ($action != 'begin' and ! $uuid) {
//            throw new \Exception('rollback,commit 方法 uuid 不能为空');
//        }
//
//        switch ($action) {
//            case 'begin' :
//                return yield $this->container->get('model')->begin();
//            case 'commit' :
//                return yield $this->container->get('model')->commit($uuid);
//            case 'rollback' :
//                return yield $this->container->get('model')->rollback($uuid);
//        }
//
//        throw new \Exception('不支持的事物方法');
    }

    /**
     * @param array $arguments
     * @return $this
     */
    protected function task(array $arguments = [])
    {
        $this->getServer()->task($arguments);

        return $this;
    }

    /**
     * @return \Weipaitang\Coroutine\Multi
     */
    protected function multi()
    {
        return $this->container->get('multi');
    }
}
