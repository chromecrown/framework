<?php

namespace Wpt\Framework\Log;

use Wpt\Framework\Support\Construct;
use Wpt\Framework\Utility\Time;
use Wpt\Framework\Utility\File as FileTool;

/**
 * Class FileHandler
 *
 * @package Wpt\Framework\Log
 */
class FileHandler implements LogHandlerInterface
{
    use Construct;

    /**
     * @param array $data
     */
    public function write(array $data)
    {
        $file = storage_path('logs/' . $data['level'] . '/' . $data['name']);
        $dir  = dirname($file);
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $message = $this->getFormatString($data);

        $flag = ($this->server->getServer()->taskworker ?? false) ? 'writeSync' : 'write';

        FileTool::$flag($file, $message, FILE_APPEND);
        unset($data, $message);
    }

    /**
     * @param $data
     * @return string
     */
    private function getFormatString(& $data)
    {
        $time  = Time::date($data['time']);
        $level = strtoupper($data['level']);

        $message = (is_array($data['message']) or is_object($data['message']))
            ? json_encode($data['message'], JSON_UNESCAPED_UNICODE)
            : (string)$data['message'];
        $message = str_replace("\n", '', $message);

        $context = json_encode($data['context'], JSON_UNESCAPED_UNICODE);

        $string  = "[{$time}]";
        $string .= " [{$level}]";
        $string .= " [{$message}]";
        $string .= " [{$context}]";
        $string .= "\n";

        unset($data, $message, $time, $level, $context);

        return $string;
    }
}
