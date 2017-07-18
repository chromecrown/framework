<?php

namespace Weipaitan\Framework\Protocol;

use Weipaitang\Config\Config;
use Weipaitang\Console\Output;
use Weipaitang\Framework\Controller;
use Weipaitang\Route\Route;
use Weipaitang\Server\Server;
use Weipaitang\Http\Request;
use Weipaitang\Http\Response;
use Weipaitang\Framework\Application;
use Weipaitang\Middleware\Middleware;
use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\Http\Response as SwooleHttpResponse;

/**
 * Class Http
 * @package Weipaitan\Framework\Protocol
 */
class Http extends Protocol
{
    /**
     * @var array
     */
    protected $register = [
        Server::ON_REQUEST => 'onRequest'
    ];

    /**
     * @param SwooleHttpRequest  $request
     * @param SwooleHttpResponse $response
     */
    public function onRequest(SwooleHttpRequest $request, SwooleHttpResponse $response)
    {
        $request  = new Request($request);
        $response = new Response($response);

        $response->withHeader('Server', 'weipaitang ' . Application::VERSION);
        $response->withHeader('Content-Type', 'application/json;charset=utf-8');

        /**
         * @var Config $config
         */
        $config = $this->app->get('config');
        $config->get('http.enable_route', false)
            ? $this->dispatchWithRoute($request, $response)
            : $this->dispatch($request, $response);
    }

    /**
     * @param Request     $request
     * @param Response    $response
     * @param string|null $controller
     * @param string|null $method
     * @param array|null  $params
     * @param array|null  $middlewares
     */
    private function dispatch(
        Request $request,
        Response $response,
        string $controller = null,
        string $method = null,
        array $params = null,
        array $middlewares = null
    ) {
        if ($controller === null) {
            list($controller, $method) = $this->parseRequest($request->getUri());

            if (! $controller) {
                $response->withStatus(404);
                $response->end();

                return;
            }

            $params      = [];
            $middlewares = $this->app->getMiddlewares();
        }

        $object = $this->app->make($controller);

        if (! method_exists($object, $method)) {
            $response->withStatus(404)->end();
            $response->end();

            return;
        }

        /**
         * @var Controller $object
         */
        $object->withRequest($request);
        $object->withResponse($response);

        $this->logRequest('http', $controller, $method, $params);

        $middlewares = array_merge([
            function (Request $request, Response $response) use ($object, $method, $params) {
                /**
                 * @var Response $response
                 */
                $response = yield $object->$method(...array_values($params));
                $response->end();

                return $response;
            }
        ], $middlewares);

        $this->dispatchRun($request, $response, $middlewares);
    }

    /**
     * @param Request  $request
     * @param Response $response
     */
    private function dispatchWithRoute(Request $request, Response $response)
    {
        /**
         * @var Route $route
         */
        $route = $this->app->get('route');

        $result = $route->parse(
            $request->getUri(),
            $request->getMethod()
        );

        if (! $result) {
            $response->withStatus(404);
            $response->end();

            return;
        }

        list($class, $params, $middlewares) = $result;
        unset($result);

        $middlewares = array_merge(
            $this->app->getMiddlewares(),
            $middlewares
        );

        if ($class instanceof \Closure) {
            $middlewares = array_merge([
                function (Request $request, Response $response) use ($class, $params) {
                    array_unshift($params, $request, $response);

                    if (DEBUG_MODEL) {
                        Output::debug('HTTP Closure ' . $request->getUri(), 'blue');
                    }

                    $startTime = time();

                    /**
                     * @var Response $response
                     */
                    $response = yield $class(...array_values($params));
                    $response->end();

                    $this->logRunInfo(
                        $response->getStatusCode() == 200,
                        (float)bcsub(microtime(true), $startTime, 7)
                    );

                    return $response;
                }
            ], $middlewares);

            $this->dispatchRun($request, $response, $middlewares);

            return;
        }

        list($controller, $method) = $class;
        $controller = '\App\Http\Controller\\'. $controller;

        $this->dispatch($request, $response, $controller, $method, $params, $middlewares);
    }

    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $middlewares
     */
    private function dispatchRun(Request $request, Response $response, array $middlewares)
    {
        try {
            $middleware = new Middleware;
            $middleware->withMiddleware($middlewares);

            $this->app->get('coroutine')->newTask(
                $middleware->dispatch($request, $response)
            )->run();
        } catch (\Exception $e) {
            $response->withStatus($e->getCode() ?: 500);
            $response->end();
        }
    }

    /**
     * @param string $uri
     *
     * @return array
     * @throws \Exception
     */
    protected function parseRequest(string $uri)
    {
        if ($uri == '' or $uri == '/') {
            $uri = 'Index';
        }

        $uri = array_map('trim', explode('/', ltrim($uri, '/')));

        $controller = ucfirst($uri[0]);

        $namespace = '\App\Http\Controller\\';

        if (class_exists($namespace . $controller)) {
            $method = $uri[1] ?? 'index';

            return [$namespace . $controller, $method];
        }

        if (! isset($uri[1])) {
            return [null, null];
        }

        $namespace .= $controller . '\\';
        $controller = ucfirst($uri[1]);

        if (class_exists($namespace . $controller)) {
            $method = $uri[2] ?? 'index';

            return [$namespace . $controller, $method];
        }

        return [null, null];
    }
}
