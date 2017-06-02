<?php

namespace Flower\Client;

use Swoole\Client as SwooleClient;

/**
 * Class Tcp
 * @package Flower\Client
 */
class Tcp
{
    /**
     * @var array
     */
    private $on;

    /**
     * @var array
     */
    private $set;

    /**
     * @var SwooleClient
     */
    private $client;

    /**
     * @param $action
     * @param $callback
     * @throws \Exception
     */
    public function on($action, $callback)
    {
        if ($this->client) {
            return;
        }

        $action = strtolower($action);

        if (! in_array($action, ['connect', 'receive', 'close', 'error'])) {
            throw new \Exception('Tcp client unknown action: '. $action);
        }

        $this->on[$action] = $callback;
    }

    /**
     * @param $host
     * @param $port
     * @param $set
     * @param $timeout
     * @param $callback
     */
    public function connect($host, $port, $set, $timeout, $callback = null)
    {
        $this->set = $set;

        if ($callback) {
            $this->on('connect', $callback);
        }

        $client = new SwooleClient(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $client->set($this->set);

        $client->on("Connect", [$this, 'onConnect']);
        $client->on("Receive", [$this, 'onReceive']);
        $client->on('Error', [$this, 'onError']);
        $client->on('Close', [$this, 'onClose']);

        $client->connect($host, $port, $timeout);
    }

    /**
     * @param $data
     * @param callable $callback
     */
    public function send($data, $callback = null)
    {
        if ($callback) {
            $this->on('receive', $callback);
        }

        $this->client->send($data);
    }

    /**
     * @return bool
     */
    public function isConnected()
    {
        return $this->client
            ? $this->client->isConnected()
            : false;
    }

    /**
     * @param SwooleClient $client
     */
    public function onConnect(SwooleClient $client)
    {
        $this->client = $client;
        $this->hook('connect', $this, true);
        unset($this->on['connect']);
    }

    /**
     * @param SwooleClient $client
     */
    public function onClose(SwooleClient $client)
    {
        $this->hook('close', $this, false);
    }

    /**
     * @param SwooleClient $client
     * @param $data
     */
    public function onReceive(SwooleClient $client, $data)
    {
        $this->hook('receive', $this, $data);
    }

    /**
     * @param SwooleClient $client
     */
    public function onError(SwooleClient $client)
    {
        if (isset($this->on['connect'])) {
            $this->hook('connect', $this, false);
        } else {
            $this->hook('receive', $this, null);
        }

        if ($client->isConnected()) {
            $client->close();
        }

        $this->hook('error', $this);
    }

    /**
     * @param $action
     * @param array ...$data
     */
    public function hook($action, ...$data)
    {
        if (! isset($this->on[$action])) {
            return;
        }

        $callback = $this->on[$action];
        if ($callback instanceof \Closure) {
            $callback(...$data);
        } elseif (is_array($callback)) {
            call_user_func_array($callback, $data);
        }
    }

    /**
     * close tcp connection
     */
    public function close()
    {
        if ($this->client->isConnected()) {
            $this->client->close();
        }
    }

    /**
     * @return SwooleClient
     */
    public function getClient()
    {
        return $this->client;
    }
}
