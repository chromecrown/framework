<?php

namespace Wpt\Framework\Utility;

/**
 * Class Time
 *
 * @package Wpt\Framework\Utility
 */
class Time
{
    /**
     * @param int  $time
     * @param bool $isLog
     * @return string
     */
    public static function format(int $time, bool $isLog = true)
    {
        if (! is_numeric($time)) {
            return '';
        }

        $value = [
            "years"   => 0,
            "days"    => 0,
            "hours"   => 0,
            "minutes" => 0,
            "seconds" => 0,
        ];

        if ($time >= 31556926) {
            $value["years"] = floor($time / 31556926);
            $time = ($time % 31556926);
        }

        if ($time >= 86400) {
            $value["days"] = floor($time / 86400);
            $time = ($time % 86400);
        }

        if ($time >= 3600) {
            $value["hours"] = floor($time / 3600);
            $time = ($time % 3600);
        }

        if ($time >= 60) {
            $value["minutes"] = floor($time / 60);
            $time = ($time % 60);
        }

        $value["seconds"] = floor($time);

        if ($isLog) {
            $t = $value["days"] . "d " . $value["hours"] . "h " . $value["minutes"] . "m " . $value["seconds"] . "s";
        } else {
            $t = $value["days"] . " days " . $value["hours"] . " hours " . $value["minutes"] . " minutes";
        }

        return $t;
    }

    /**
     * @return float
     */
    public static function millisecond()
    {
        return (float)sprintf('%.0f', array_sum(array_map('floatval', explode(' ', microtime()))) * 1000);
    }

    /**
     * @param int    $millisecond
     * @param string $format
     * @param bool   $appendMillisecond
     * @return string
     */
    public static function date(int $millisecond, string $format = 'Y-m-d H:i:s', bool $appendMillisecond = true)
    {
        $date = date($format, substr($millisecond, 0, 10));
        if ($appendMillisecond) {
            $date .= ' ' . substr($millisecond, 10);
        }

        return $date;
    }
}
