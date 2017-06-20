<?php

namespace Flower\Middleware;

use Flower\Http\Request;
use Flower\Http\Response;

interface MiddlewareInterface
{
    public function handler(Request $request, Response $response, callable $next);
}
