<?php

namespace Wpt\Framework\Client\Async;

use Wpt\Framework\Coroutine\CoroutineInterface;

/**
 * Class Base
 * @package Wpt\Framework\Client\Async
 */
abstract class Base implements CoroutineInterface
{
    /**
     * @var int
     */
    protected $timer;

    /**
     * @var int
     */
    protected $timeout;

    /**
     * @var callable
     */
    protected $callback;

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
                ($this->callback)(null);
            }
        );
    }

    /**
     * clear tick
     */
    protected function clearTick()
    {
        if ($this->timer) {
            swoole_timer_clear($this->timer);
        }

        // reset
        $this->timer = null;
    }

    /**
     * @param int $timeout
     *
     * @return $this
     */
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout * 1000;

        return $this;
    }
}


