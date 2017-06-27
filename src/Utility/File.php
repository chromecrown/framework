<?php

namespace Wpt\Framework\Utility;

/**
 * Class File
 *
 * @package Wpt\Framework\Utility
 */
class File
{
    /**
     * 异步写入文件
     *
     * @param string   $file
     * @param string   $content
     * @param int      $flag
     * @param callable $callback
     */
    public static function write(string $file, string $content, int $flag = 0, $callback = null)
    {
        if (version_compare(SWOOLE_VERSION, '1.9.2', '>=')) {
            \Swoole\Async::writeFile($file, $content, $callback, $flag);
        } else {
            self::writeSync($file, $content, $flag, $callback);
        }
    }

    /**
     * 异步读文件
     *
     * @param string   $file
     * @param callable $callback
     */
    public static function read(string $file, callable $callback)
    {
        if (version_compare(SWOOLE_VERSION, '1.9.2', '>=')) {
            \Swoole\Async::readFile($file, $callback);
        } else {
            self::readSync($file, $callback);
        }
    }

    /**
     * 同步写入文件
     *
     * @param string   $file
     * @param string   $content
     * @param int      $flag
     * @param callable $callback
     */
    public static function writeSync(string $file, string $content, int $flag = 0, $callback = null)
    {
        file_put_contents($file, $content, $flag);

        is_callable($callback) and $callback($file);
    }

    /**
     * 同步读文件
     *
     * @param string   $file
     * @param callable $callback
     */
    public static function readSync(string $file, callable $callback)
    {
        $callback($file, file_get_contents($file));
    }
}
