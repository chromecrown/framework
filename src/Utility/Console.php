<?php

namespace Flower\Utility;

class Console
{
    /**
     * 设置进程名
     *
     * @param  string $title
     * @return void
     */
    public static function setProcessTitle($title = '')
    {
        static $host, $port, $appName = null;

        if (PHP_OS == 'Darwin') {
            return;
        }

        // 获取服务名
        if ($appName == null) {
            $host    = app('config')->get('tcp_server_ip', '127.0.0.1');
            $port    = app('config')->get('tcp_server_port', '9501');
            $appName = app('server')->getServerName();
        }

        $setTitle  = $appName. "[{$host}:{$port}]";
        $setTitle .= $title ? '|' : '';
        $setTitle .= $title;

        if (function_exists('cli_set_process_title')) {
            @cli_set_process_title($setTitle);
        } else {
            @swoole_set_process_name($setTitle);
        }
    }

    /**
     * 获取当前用户
     *
     * @return mixed
     */
    public static function getCurrentUser()
    {
        return posix_getpwuid(posix_getuid())['name'];
    }

    /**
     * 改变进程的用户ID
     *
     * @param $user
     */
    public static function changeUser($user)
    {
        $currentUser = self::getCurrentUser();

        if ($currentUser != $user) {
            $user = posix_getpwnam($user);
            if ($user) {
                posix_setuid($user['uid']);
                posix_setgid($user['gid']);
            }
        }
    }

    /**
     * @param string $string
     * @param null $color
     */
    public static function debug(string $string, $color = null)
    {
        if (! DEBUG_MODEL) {
            return;
        }

        if (strpos($string, '^^') === false) {
            if ($color) {
                $string = "^^[Debug]$$ {$string}";
            } else {
                $string = "[Debug] {$string}";
            }
        }

        self::write($string. "\n", $color);
    }

    /**
     * 控制台输出
     * 例：^^ color text $$ normal text.....
     *
     * @param string  $string
     * @param null    $color
     */
    public static function write(string $string, $color = null)
    {
        parseColor:
        $prefix = $suffix = '';
        if ($color) {
            $color = strtolower(trim($color));
            switch ($color) {
                case 'blue' :
                    $prefix = "\033[44;37;1m";
                    break;
                case 'green' :
                    $prefix = "\033[42;37;1m";
                    break;
                case 'red' :
                    $prefix = "\033[41;37;1m";
                    break;
                case 'yellow' :
                    $prefix = "\033[43;37;1m";
                    break;
                default:
                    $color  = ucfirst($color);
                    $string = "^^[{$color}]$$ ". $string;
                    $color  = 'blue';
                    goto parseColor;
                    break;
            }

            if ($prefix) {
                $suffix = "\033[0m";
            }

            if (strpos($string, '^^') === false) {
                $hasEof = mb_substr($string, -1, 1) === "\n";
                $string = rtrim($string, "\n");
                $string = '^^'. $string. '$$';
                if ($hasEof) {
                    $string .= "\n";
                }
            }

            $string = str_replace(['^^', '$$'], [$prefix, $suffix], $string);
        }

        if (mb_substr($string, -1, 1) !== "\n") {
            $string .= "\n";
        }

        $date = date('H:i:s');

        echo "[$date] $string";
    }

    /**
     * 替换输出
     *
     * @param $message
     * @param null    $forceClearLines
     */
    public static function writeReplace($message, $forceClearLines = null)
    {
        static $lastLines = 0;

        if (null != $forceClearLines) {
            $lastLines = $forceClearLines;
        }

        $termWidth = exec('tput cols', $toss, $status);
        if ($status) {
            // Arbitrary fall-back term width.
            $termWidth = 64;
        }

        $lineCount = 0;
        foreach (explode("\n", $message) as $line) {
            $lineCount += count(str_split($line, $termWidth));
        }

        // clear
        echo "\033[2J";
        echo "\033[H";

        // Erasure MAGIC: Clear as many lines as the last output had.
        for ($i = 0; $i < $lastLines; $i++) {
            // Return to the beginning of the line
            echo "\r";
            // Erase to the end of the line
            echo "\033[K";
            // Move cursor Up a line
            echo "\033[1A";
            // Return to the beginning of the line
            echo "\r";
            // Erase to the end of the line
            echo "\033[K";
            // Return to the beginning of the line
            echo "\r";
            // Can be consolodated into
            // echo "\r\033[K\033[1A\r\033[K\r";
        }

        $lastLines = $lineCount;

        echo $message. "\n";
    }
}
