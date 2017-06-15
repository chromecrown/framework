<?php

namespace Flower\Log;

use Flower\Support\Construct;
use Flower\Utility\Time;
use Flower\Utility\File as FileTool;

/**
 * Class FileHandler
 *
 * @package Flower\Log
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
        if ($data['client']) {
            $string .= " [{$data['client']} ({$data['client_host']})]";
            $string .= " [{$data['request']}]";
        }
        $string .= " [{$context}]";
        $string .= "\n";

        unset($data, $message, $time, $level, $context);

        return $string;
    }
}
