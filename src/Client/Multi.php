<?php

namespace Wpt\Framework\Client;

use Wpt\Framework\Client\Async\Base;
use Wpt\Framework\Core\Application;

/**
 * Class Multi
 *
 * @package Wpt\Framework\Client
 */
class Multi extends Base
{
    /**
     * @var \Wpt\Framework\Coroutine\Scheduler
     */
    private $scheduler = null;

    private $request  = [];
    private $result   = [];

    /**
     * Multi constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->scheduler = $app->get('co.scheduler');
    }

    /**
     * @param string     $key
     * @param \Generator $generator
     */
    public function add(string $key, \Generator $generator)
    {
        $this->request[] = $key;

        $this->scheduler->newTask(
            (function () use ($key, $generator) {
                $this->result[$key] = yield $generator;

                if (count($this->result) == count($this->request)) {
                    $this->callback();
                }
            })()
        );
    }

    /**
     * 超时计时器
     *
     * @param null $client
     */
    protected function startTick($client = null)
    {
        $this->timer = swoole_timer_after(
            floatval($this->timeout ?: 3) * 1000,
            function () {
                $this->callback();
            }
        );
    }

    /**
     * @param callable $callback
     */
    public function send(callable $callback)
    {
        $this->callback = $callback;

        $this->startTick();

        $this->scheduler->run();
    }

    /**
     * callback
     */
    public function callback()
    {
        $this->clearTick();

        if (! $this->callback) {
            return;
        }

        $callback = $this->callback;
        $this->callback = null;

        foreach ($this->request as $key) {
            if (! array_key_exists($key, $this->result)) {
                $this->result[$key] = null;
            }
        }

        $callback($this->result);

        $this->result   = [];
        $this->request  = [];
    }
}
