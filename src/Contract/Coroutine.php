<?php

namespace Flower\Contract;

/**
 * Interface Coroutine
 *
 * @package Flower\Contract
 */
interface Coroutine
{
    /**
     * @param callable $callback
     */
    function send(callable $callback);
}
