<?php

use Flower\Core\Container;

if (! function_exists('app')) {
    function app($class = null, ...$arguments)
    {
        return Container::getInstance()->get($class ?: 'app', $arguments);
    }
}

if (! function_exists('config')) {
    /**
     * @return \Flower\Core\Config
     */
    function config()
    {
        return app('config');
    }
}

if (! function_exists('multi')) {
    /**
     * @return \Flower\Client\Multi
     */
    function multi()
    {
        return app('multi');
    }
}

if (! function_exists('redis')) {
    /**
     * @return \Flower\Client\Redis
     */
    function redis()
    {
        return app('redis');
    }
}

if (! function_exists('root_path')) {
    /**
     * @param string $path
     * @return string
     */
    function root_path($path = null)
    {
        static $basePath = null;

        if ($basePath == null) {
            $basePath = defined('ROOT_PATH') ? ROOT_PATH : realpath(__DIR__ . '/../../../../../');
            $basePath = rtrim($basePath, '/') . '/';
        }

        $path and ($path = ltrim($path, '/'));

        return $basePath . $path;
    }
}

if (! function_exists('app_path')) {
    /**
     * @param string $path
     * @return string
     */
    function app_path($path = null)
    {
        static $appPath = null;

        if ($appPath == null) {
            $appPath = defined('APP_PATH') ? APP_PATH : realpath(root_path() . 'app');
            $appPath = rtrim($appPath, '/') . '/';
        }

        $path and ($path = ltrim($path, '/'));

        return $appPath . $path;
    }
}

if (! function_exists('storage_path')) {
    /**
     * @param string $path
     * @return string
     */
    function storage_path($path = null)
    {
        static $storagePath = null;

        if ($storagePath == null) {
            $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : realpath(root_path() . 'storage');
            $storagePath = rtrim($storagePath, '/') . '/';
        }

        $path and ($path = ltrim($path, '/'));

        return $storagePath . $path;
    }
}
