<?php

namespace Weipaitang\Framework\Dispatcher;

use Weipaitang\Framework\Support\Construct;

abstract class Base
{
    use Construct;

    /**
     * @param string $request
     * @param string $method
     * @param array  $arguments
     * @return string
     */
    protected function getRequestString(string $request, string $method, array $arguments = [])
    {
        $params = substr(json_encode(array_values($arguments)), 1, -1);

        return $request . ':' . $method . '(' . $params . ')';
    }
}