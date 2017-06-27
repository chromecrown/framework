<?php

namespace Wpt\Framework\Coroutine;

/**
 * Interface CoroutineInterface
 *
 * @package Wpt\Framework\Coroutine
 */
interface CoroutineInterface
{
    /**
     * @param callable $callback
     */
    function send(callable $callback);
}
