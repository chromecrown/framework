<?php

namespace Wpt\Framework\Middleware;

use Wpt\Framework\Http\Request;
use Wpt\Framework\Http\Response;

interface MiddlewareInterface
{
    public function handler(Request $request, Response $response, callable $next);
}
