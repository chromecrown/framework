<?php

namespace Flower\Dispatcher;

use Flower\Core\Controller;
use Flower\Http\Request;
use Flower\Http\Response;
use Flower\Support\Define;
use Flower\Utility\Console;
use Swoole\Http\Response as SwooleHttpResponse;

/**
 * Class Http
 *
 * @package Flower\Dispatcher
 */
class Http extends Base
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var SwooleHttpResponse
     */
    private $swooleHttpResponse;

    /**
     * @param Request  $request
     * @param SwooleHttpResponse $response
     *
     * @throws \Exception
     */
    public function dispatch(Request $request, SwooleHttpResponse $response)
    {
        $this->request  = $request;
        $this->response = $this->app->get('response');
        $this->swooleHttpResponse = $response;

        $this->response->withHeader('Server', 'flower ' . Define::VERSION);
        $this->response->withHeader('Content-Type', 'application/json;charset=utf-8');

        if ($this->app['config']->get('enable_route', false)) {
            $result = $this->app['route']->parse(
                $this->request->getUri(),
                $this->request->getMethod()
            );

            if (! $result) {
                throw new \Exception('Http Request Not Found.', 404);
            }

            list($class, $params, $middleware) = $result;
            unset($result);

            if ($middleware) {
                foreach ($middleware as &$value) {
                    $value = $this->app->getMiddleware($value);
                }
                unset($value);
            }

            if ($class instanceof \Closure) {
                $middleware = array_merge([
                    function (Request $request, Response $response) use ($class, $params) {
                        array_unshift($params, $request, $response);

                        if (DEBUG_MODEL) {
                            Console::debug('HTTP Closure ' . $request->getUri(), 'blue');
                        }

                        $startTime = time();

                        /**
                         * @var Response $response
                         */
                        $response = yield $class(...array_values($params));

                        // log run info
                        $this->app->logRunInfo(
                            $response->getStatusCode() == 200,
                            (float)bcsub(microtime(true), $startTime, 7)
                        );

                        return $response;
                    }
                ], $middleware);

                $this->dispatchRun($middleware);

                return;
            }

            list($controller, $method) = $class;
            $controller = '\App\Http\Controller\\'. $controller;
        } else {
            list($controller, $method) = $this->parseRequest($request->getUri());

            $params     = [];
            $middleware = $this->app->getMiddleware();
        }

        $this->request->withRequestController($controller);
        $this->request->withRequestMethod($method);

        $this->dispatchWithControllerName($controller, $method, $params, $middleware);
    }

    /**
     * @param string $controller
     * @param string $method
     * @param array  $params
     * @param array  $middleware
     * @throws \Exception
     */
    private function dispatchWithControllerName(string $controller, string $method, array $params, array $middleware)
    {
        $object = $this->app->make($controller);

        // 请求的对象木有找到
        if (! method_exists($object, $method)) {
            throw new \Exception('Http Request Not Found.', 404);
        }

        /**
         * @var Controller $object
         */
        $object->withHttp($this->request, $this->response);

        if (DEBUG_MODEL) {
            Console::debug('HTTP ' . $this->getRequestString($controller, $method), 'blue');
        }

        $middleware = array_merge([
            function (Request $request, Response $response) use ($object, $method, $params) {
                return yield $object->$method(...array_values($params));
            }
        ], $middleware);

        $this->dispatchRun($middleware);
    }

    /**
     * @param $middleware
     */
    private function dispatchRun(array $middleware)
    {
        $this->app->get('co.scheduler')->newTask(
            $this->app->get('middleware')
                ->add($middleware)
                ->run($this->request, $this->response, $this->swooleHttpResponse)
        )->run();
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
            throw new \Exception('Http Request Not Found.', 404);
        }

        $namespace .= $controller . '\\';
        $controller = ucfirst($uri[1]);

        if (class_exists($namespace . $controller)) {
            $method = $uri[2] ?? 'index';

            return [$namespace . $controller, $method];
        }

        throw new \Exception('Http Request Not Found.', 404);
    }
}
