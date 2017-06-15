<?php

namespace Flower\Client;

use Flower\Core\Application;
use Flower\Coroutine\CoroutineInterface;

/**
 * Class Multi
 *
 * @package Flower\Client
 */
class Multi implements CoroutineInterface
{
    /**
     * @var \Flower\Coroutine\Scheduler
     */
    private $scheduler = null;

    /**
     * @var callable
     */
    private $callback;

    private $request  = [];
    private $result   = [];
    private $counter  = 0;

    private $timer;
    private $timeout;

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
        $this->counter++;
        $this->request[] = $key;

        $this->scheduler->newTask(
            (function () use ($key, $generator) {
                $this->result[$key] = yield $generator;

                if (count($this->result) == $this->counter) {
                    $this->callback();
                }
            })()
        );
    }

    /**
     * @param $timeout
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout * 1000;

        return $this;
    }

    /**
     * @param callable $callback
     */
    public function send(callable $callback)
    {
        $this->callback = $callback;

        // timeout tick
        $this->timer = swoole_timer_after(
            $this->timeout ?: 3000,
            function () {
                $this->callback();
            }
        );

        $this->scheduler->run();
    }

    /**
     * callback
     */
    public function callback()
    {
        if (! $this->timer) {
            return;
        }

        $this->timer = null;

        foreach ($this->request as $key) {
            if (! array_key_exists($key, $this->result)) {
                $this->result[$key] = nil;
            }
        }

        // timeout callback
        ($this->callback)($this->result);

        $this->result   = [];
        $this->request  = [];
        $this->counter  = 0;
        $this->callback = null;
    }

    /**
     * Multi destructor.
     */
    public function __destruct()
    {
        unset($this->schedule, $this->callback, $this->result, $this->counter);
    }
}
