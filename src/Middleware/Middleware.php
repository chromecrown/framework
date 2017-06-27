<?php

namespace Wpt\Framework\Middleware;

use Wpt\Framework\Http\Request;
use Wpt\Framework\Http\Response;
use Wpt\Framework\Core\Application;
use Swoole\Http\Response as SwooleHttpResponse;

/**
 * Class Middleware
 *
 * @package Wpt\Framework\Middleware
 */
class Middleware
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @var array
     */
    private $middleware = [];

    /**
     * Middleware constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @param \Closure|string $middleware
     * @return $this
     */
    public function add($middleware)
    {
        if (! is_array($middleware)) {
            $middleware = [$middleware];
        }

        array_map(function ($item) {
            $this->middleware[] = $item;
        }, $middleware);

        return $this;
    }

    /**
     * @param Request            $request
     * @param Response           $response
     * @param SwooleHttpResponse $swooleHttpResponse
     */
    public function run(Request $request, Response $response, SwooleHttpResponse $swooleHttpResponse)
    {
        $this->middleware = array_reverse($this->middleware);

        $resolved = $this->resolve(0);

        /**
         * @var Response $response
         */
        $response = yield $resolved($request, $response);

        $headers = $response->getHeaders();
        $headers and array_walk($headers, function ($value, $key) use ($swooleHttpResponse) {
            $swooleHttpResponse->header($key, $value);
        });

        $swooleHttpResponse->status($response->getStatusCode());
        $swooleHttpResponse->end($response->getContent());
    }

    /**
     * @param int $index
     * @return \Closure
     */
    private function resolve(int $index)
    {
        return function (Request $request, Response $response) use ($index) {
            $middleware = $this->middleware[$index];
            $middleware = $middleware instanceof \Closure
                ? $middleware
                : $this->app->make($middleware);

            if ($middleware instanceof MiddlewareInterface) {
                $next = $this->resolve($index + 1);

                $res = yield $middleware->handler($request, $response, $next);
            } else {
                $res = yield $middleware($request, $response);
            }

            if (! ($res instanceof Response)) {
                throw new MiddlewareException('Controller,Middleware return value must instanceof Wpt\Framework\Http\Response', 500);
            }

            return $res;
        };
    }
}
