<?php

namespace Flower\Dispatcher;

use Flower\Support\Construct;

abstract class Base
{
    use Construct;

    /**
     * @param $request
     * @param $method
     * @param $arguments
     * @return string
     */
    protected function getRequestString($request, $method, $arguments = [])
    {
        $params = substr(json_encode(array_values($arguments)), 1, -1);

        return $request . ':' . $method . '(' . $params . ')';
    }
}