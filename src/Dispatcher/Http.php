<?php

namespace Flower\Dispatcher;

use Flower\Support\Define;
use Flower\Utility\Console;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Class Http
 * @package Flower\Dispatcher
 */
class Http extends Base
{
    /**
     * @param Request $request
     * @param Response $response
     * @throws \Exception
     */
    public function dispatch(Request $request, Response $response)
    {
        list($controller, $method) = $this->parseRequest($request);

        $object = $this->app->make($controller);

        // 请求的对象木有找到
        if (! method_exists($object, $method)) {
            throw new \Exception('Http Request Not Found.');
        }

        $object->setHttp($request, $response);
        $response->header('Server', 'flower '. Define::VERSION);
        $response->header('Content-Type', 'application/json;charset=utf-8');

        Console::debug('HTTP '. $this->getRequestString($controller, $method), 'blue');

        $generator = $object->$method();

        if ($generator instanceof \Generator) {
            $this->app->get('co.scheduler')->newTask($generator)->run();
        }
    }

    /**
     * @param $request
     * @return array
     * @throws \Exception
     */
    protected function parseRequest($request)
    {
        $uri = trim($request->server['request_uri'] ?? '', '/');

        if ($uri == '' or $uri == '/') {
            $uri = 'Index';
        }

        $namespace = '\App\Controller\Http\\';

        $uri = array_map('trim', explode('/', $uri));

        $controller = ucfirst($uri[0]);

        if (class_exists($namespace. $controller)) {
            $method = $uri[1] ?? 'index';

            return [$namespace. $controller, $method];
        }

        if (! isset($uri[1])) {
            throw new \Exception('Http Request Not Found.');
        }

        $namespace .= $controller. '\\';
        $controller = ucfirst($uri[1]);

        if (class_exists($namespace. $controller)) {
            $method = $uri[2] ?? 'index';

            return [$namespace. $controller, $method];
        }

        throw new \Exception('Http Request Not Found.');
    }
}
