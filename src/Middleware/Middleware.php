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
     * @var array
     */
    private $resolved = [];

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

        $response = yield $resolved($request, $response);

        if (! $response instanceof Response) {
            throw new MiddlewareException('Middleware return value must instanceof Swoole\Http\Response');
        }

        $response->end();
    }

    /**
     * @param int $index
     * @return \Closure
     */
    private function resolve(int $index)
    {
        return function (Request $request, Response $response) use ($index) {
            if (! isset($this->resolved[$index])) {
                $item = $this->middleware[$index];

                $this->resolved[$index] = $item instanceof \Closure
                    ? $item
                    : $this->app->make($item);
            }

            $middleware = $this->resolved[$index];

            if ($middleware instanceof MiddlewareInterface) {
                $next = $this->resolve($index + 1);

                return yield $middleware->handler($request, $response, $next);
            }

            return yield $middleware($request, $response);
        };
    }
}
