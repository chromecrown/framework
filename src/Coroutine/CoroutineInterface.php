<?php

namespace Flower\Coroutine;

/**
 * Interface CoroutineInterface
 *
 * @package Flower\Coroutine
 */
interface CoroutineInterface
{
    /**
     * @param callable $callback
     */
    function send(callable $callback);
}
