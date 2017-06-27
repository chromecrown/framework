<?php

namespace Wpt\Framework\Core;

use Wpt\Framework\Log\Log;
use Wpt\Framework\Utility\Console;

/**
 * Class Config
 *
 * @package Wpt\Framework\Core
 */
class Config
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * 把 config 下的配置都加载进来
     */
    public function __construct()
    {
        $files = scandir(root_path('/config/'));
        foreach ($files as $file) {
            if ($file == '.' or $file == '..') {
                continue;
            }

            $this->load(explode('.', $file)[0]);
        }
    }

    /**
     * 加载配置文件
     *
     * @param string $file
     * @param bool   $return
     * @return mixed
     */
    public function load(string $file, bool $return = false)
    {
        if (! isset($this->config[$file])) {
            $filePath = root_path("/config/{$file}.php");

            if (file_exists($filePath)) {
                $this->config[$file] = include $filePath;
            } else {
                // server 配置文件不存在，则终止服务
                if ($file == 'server') {
                    Console::write("Config file not found. file: server", 'red');
                    exit(1);
                }

                $message = "Config file not found. file: {$file}";
                Log::error($message);

                if (DEBUG_MODEL) {
                    Console::debug($message, 'red');
                }
                unset($message);

                $this->config[$file] = [];
            }
        }

        if ($return) {
            return $this->config[$file];
        }
    }

    /**
     * 获取配置
     *
     * @param string $key
     * @param mixed  $default
     * @return null
     */
    public function get(string $key, $default = null)
    {
        list($file, $key) = $this->parseKey($key);

        return $this->config[$file][$key] ?? $default;
    }

    /**
     * 设置配置
     *
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function set(string $key, $value)
    {
        list($file, $key) = $this->parseKey($key);

        $this->config[$file][$key] = $value;

        return $this;
    }

    /**
     * 是否存在配置
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key)
    {
        list($file, $key) = $this->parseKey($key);

        return isset($this->config[$file][$key]);
    }

    /**
     * 移除配置
     *
     * @param string $key
     */
    public function del(string $key)
    {
        if (! $this->has($key)) {
            return;
        }

        list($file, $key) = $this->parseKey($key);
        unset($this->config[$file][$key]);
    }

    /**
     * @param string $key
     * @return array
     */
    private function parseKey(string $key)
    {
        $file = 'server';
        if (strpos($key, '/') !== false) {
            list($file, $key) = explode('/', $key);
        }

        return [$file, $key];
    }
}
