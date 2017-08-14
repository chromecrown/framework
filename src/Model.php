<?php

namespace Weipaitang\Framework;

use Weipaitang\Container\Container;

/**
 * Class Model
 * @package Weipaitang\Framework
 */
abstract class Model extends AbstractBase
{
    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        if (! method_exists($this, $name)) {
            throw new \Exception("Model method not found. [{$name}]");
        }

        return $this->$name(...$arguments);
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return Container::getInstance()
            ->make(static::class)
            ->__call($name, $arguments);
    }
}
