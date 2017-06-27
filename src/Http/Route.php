<?php

namespace Wpt\Framework\Http;

/**
 * Class Route
 * Based on https://github.com/noahbuscher/Macaw
 *
 * @package Wpt\Framework\Http
 *
 * @method Route get($uri, $callback, $middleware = [])
 * @method Route post($uri, $callback, $middleware = [])
 * @method Route put($uri, $callback, $middleware = [])
 * @method Route delete($uri, $callback, $middleware = [])
 * @method Route patch($uri, $callback, $middleware = [])
 * @method Route options($uri, $callback, $middleware = [])
 * @method Route any($uri, $callback, $middleware = [])
 */
class Route
{
    /**
     * @var bool
     */
    private $init = false;

    /**
     * @var array
     */
    private $routes = [];

    /**
     * @var array
     */
    private $methods = [];

    /**
     * @var array
     */
    private $callbacks = [];

    /**
     * @var array
     */
    private $middleware = [];

    /**
     * @var array
     */
    private $searches = [];

    /**
     * @var array
     */
    private $replaces = [];

    /**
     * @var int
     */
    private $groupId = 0;

    /**
     * @var array
     */
    private $group = [];

    /**
     * @var string|callable
     */
    private $errorCallback;

    /**
     * @var array
     */
    protected $patterns = [
        ':any' => '[^/]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    ];

    /**
     * @param $uri
     * @param $method
     * @return array
     */
    public function parse($uri, $method)
    {
        if (! $this->init) {
            $this->routes   = preg_replace('/\/+/', '/', $this->routes);
            $this->searches = array_keys($this->patterns);
            $this->replaces = array_values($this->patterns);

            $this->init = true;
        }

        $result = in_array($uri, $this->routes)
            ? $this->findWithString($uri, $method)
            : $this->findWithRegex($uri, $method);

        if ($result) {
            $result[2] = is_int($result[2])
                ? $this->group[$result[2]]
                : $result[2];

            return $result;
        }

        if (! $this->errorCallback) {
            return [];
        }

        $route = $this->errorCallback instanceof \Closure
            ? $this->errorCallback
            : explode('@', str_replace('/', '\\', $this->errorCallback));

        return [$route, [], []];
    }

    /**
     * @param $uri
     * @param $method
     * @return array
     */
    private function findWithString($uri, $method)
    {
        $routes = array_keys($this->routes, $uri);
        foreach ($routes as $pos) {
            if ($this->methods[$pos] != $method and $this->methods[$pos] != 'ANY') {
                continue;
            }

            return $this->findWithPosition($pos);
        }

        return [];
    }

    /**
     * @param $uri
     * @param $method
     * @return array
     */
    private function findWithRegex($uri, $method)
    {
        foreach ($this->routes as $pos => $route) {
            if (strpos($route, ':') !== false) {
                $route = str_replace($this->searches, $this->replaces, $route);
            }

            if (! preg_match('#^' . $route . '$#', $uri, $matched)) {
                continue;
            }

            if ($this->methods[$pos] != $method and $this->methods[$pos] != 'ANY') {
                continue;
            }

            array_shift($matched);

            return $this->findWithPosition($pos, $matched);
        }

        return [];
    }

    /**
     * @param $pos
     * @param array $matched
     * @return array
     */
    private function findWithPosition($pos, $matched = [])
    {
        $route = $this->callbacks[$pos] instanceof \Closure
            ? $this->callbacks[$pos]
            : explode('@', str_replace('/', '\\', $this->callbacks[$pos]));

        return [$route, $matched, $this->middleware[$pos]];
    }

    /**
     * @param $callback
     * @return $this
     */
    public function error($callback)
    {
        $this->errorCallback = $callback;

        return $this;
    }

    /**
     * @param callable $callback
     * @param array $middleware
     * @return $this
     */
    public function group(callable $callback, $middleware = [])
    {
        $this->groupId = array_push($this->group, $middleware) - 1;
        $callback($this);
        $this->groupId = 0;

        return $this;
    }

    /**
     * @param $name
     * @param $arguments
     * @return $this
     */
    public function __call($name, $arguments)
    {
        $this->methods[]    = strtoupper($name);
        $this->routes[]     = '/'. $arguments[0];
        $this->callbacks[]  = $arguments[1];
        $this->middleware[] = $arguments[2] ?? $this->groupId ?: [];

        return $this;
    }
}
