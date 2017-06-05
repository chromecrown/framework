<?php

namespace Flower\Pool;

use Flower\Log\Log;
use Flower\Contract\Pool as IPool;

/**
 * Class Pool
 *
 * @package Flower\Pool
 */
abstract class Pool implements IPool
{
    /**
     * @var \SplQueue
     */
    protected $pool;

    /**
     * @var \SplQueue
     */
    protected $commands;

    /**
     * @var array
     */
    protected $callbacks;

    /**
     * @var array
     */
    protected $timeTicks;

    /**
     * @var int
     */
    protected $token = 0;

    /**
     * @var int
     */
    protected $maxRetry = 3;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var int
     */
    protected $currConnect = 0;

    /**
     * @var int
     */
    protected $waitConnect = 0;

    /**
     * @var mixed
     */
    protected $failure = null;

    /**
     * @var int 0 ~ 100
     */
    protected $gcLevel = 1;

    /**
     * Pool constructor.
     *
     * @param string $name
     * @param array  $config
     */
    public function __construct(string $name = '', array $config = [])
    {
        $name and $this->setName($name);
        $config and $this->setConfig($config);

        $this->gcLevel = app('config')->get('gc_level', 1);
        if ($this->gcLevel > 100) {
            $this->gcLevel = 100;
        } elseif ($this->gcLevel < 0) {
            $this->gcLevel = 0;
        }
    }

    /**
     * init
     */
    public function init()
    {
        $this->pool = new \SplQueue();
        $this->commands = new \SplQueue();
        $this->callbacks = [];

        // 初始化连接
        if ($this->config['connection']['init'] > 0) {
            if ($this->config['connection']['init'] > $this->config['connection']['max']) {
                $this->config['connection']['init'] = $this->config['connection']['max'];
            }

            for ($i = 0; $i < $this->config['connection']['init']; $i++) {
                $this->newConnection();
            }
        }

        return $this;
    }

    /**
     * new connection
     */
    protected function newConnection()
    {
        if (($this->currConnect + $this->waitConnect) >= $this->config['connection']['max']) {
            return;
        }

        $this->connect();
    }

    /**
     * free connection
     */
    protected function freeConnection()
    {
        if (! isset($this->config['connection']['idle'])) {
            return;
        }

        $poolNum = $this->pool->count();
        if ($poolNum <= $this->config['connection']['idle']) {
            return;
        }

        $freeNum = $poolNum - $this->config['connection']['idle'];
        for ($i = 0; $i < $freeNum; $i++) {
            if ($this->pool->isEmpty()) {
                break;
            }

            $client = $this->pool->shift();
            $client->close();
            unset($client);
        }
    }

    /**
     * @return bool|mixed
     */
    public function getConnection()
    {
        if ($this->pool->isEmpty()) {
            $this->newConnection();

            return false;
        }

        if (rand(0, 100) <= $this->gcLevel) {
            $this->freeConnection();
        }

        $client = $this->pool->shift();

        if (method_exists($client, 'isConnected')) {
            if ($client->isConnected()) {
                return $client;
            }

            $this->currConnect--;
            unset($client);

            return false;
        }

        if ($client->isConnected) {
            return $client;
        }

        $this->currConnect--;
        unset($client);

        return false;
    }

    /**
     * @param      $callback
     * @param bool $useTimeout
     * @return int
     */
    protected function getToken($callback, $useTimeout = false)
    {
        $token = $this->token;

        $this->callbacks[$this->token] = $callback;

        // timeout
        if ($useTimeout) {
            $this->timeTicks[$token] = swoole_timer_after(
                $this->getTimeout(true),
                function ($token) {
                    unset($this->timeTicks[$token]);
                    $this->callback($token, $this->failure);
                },
                $token
            );
        }

        $this->token++;

        return $token;
    }

    /**
     * @param int   $token
     * @param mixed $data
     */
    protected function callback($token, $data)
    {
        if (! isset($this->callbacks[$token])) {
            return;
        }

        $callback = $this->callbacks[$token];
        unset($this->callbacks[$token]);

        if ($callback != null) {
            if (isset($this->timeTicks[$token])) {
                swoole_timer_clear($this->timeTicks[$token]);
                unset($this->timeTicks[$token]);
            }

            $callback($data);
        }

        unset($data);
    }

    /**
     * @param $data
     */
    protected function retry($data)
    {
        if ($data['retry'] >= $this->maxRetry) {
            Log::info($this->getType() . ' : retry failure, ' . $data['sql']);
            $this->callback($data['token'], $this->failure);

            return;
        }

        $data['retry']++;

        $this->commands->push($data);
        $this->newConnection();
    }

    /**
     * @param bool $microtime
     * @return int
     */
    protected function getTimeout($microtime = false)
    {
        return ($this->config['timeout'] ?? 1) * ($microtime ? 1000 : 1);
    }

    /**
     * @param $client
     */
    protected function release($client)
    {
        $this->pool->push($client);

        // 有残留的任务
        if (count($this->commands) > 0) {
            $this->execute($this->commands->shift());
        }
    }

    /**
     * @return int
     */
    public function getCurrConnect()
    {
        return $this->currConnect;
    }

    /**
     * @param array $config
     */
    public function setConfig(array $config)
    {
        if (! isset($config['connection'])) {
            $config['connection'] = [
                'init' => 0,
                'idle' => 10,
                'max'  => 128,
            ];
        }

        $this->config = $config;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }
}
