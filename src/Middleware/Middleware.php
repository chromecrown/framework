<?php

namespace Flower\Middleware;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Flower\Core\Application;

/**
 * Class Middleware
 *
 * @package Flower\Middleware
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
     * @param Request  $request
     * @param Response $response
     * @throws MiddlewareException
     */
    public function run(Request $request, Response $response)
    {
        $this->middleware = array_reverse($this->middleware);

        $resolved = $this->resolve(0);

        /**
         * @var Response $response
         */
        $response = yield $resolved($request, $response);
        $response->end();
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
                throw new MiddlewareException('Controller,Middleware return value must instanceof Swoole\Http\Response');
            }

            return $res;
        };
    }
}
