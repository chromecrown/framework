<?php

namespace Flower\Middleware;

use Swoole\Http\Request;
use Swoole\Http\Response;

interface MiddlewareInterface
{
    public function handler(Request $request, Response $response, callable $next);
}
