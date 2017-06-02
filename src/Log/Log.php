<?php

namespace Flower\Log;

use Flower\Utility\Time;
use Flower\Core\Application;

/**
 * Class Log
 * @package Flower\Core
 */
class Log
{
    /**
     * @var array
     */
    private static $logLevel = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    /**
     * @var Application
     */
    private $app;

    /**
     * @var string
     */
    private $appName;

    /**
     * @var bool
     */
    private $logHandler;

    /**
     * Log constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app     = $app;
        $this->appName = $app['server']->getServerName();
        $this->host    = $app['config']->get('server_ip', '127.0.0.1')
            . '_'. $app['config']->get('tcp_server_port', '9501');

        $this->logHandler = $app['config']->get('log_handler', 'file');
    }

    /**
     * @param string $level
     * @param $message
     * @param array $context
     * @param null $logName
     */
    public function log(string $level, $message, $context = [], $logName = null)
    {
        $logName = $logName ?: date('Ym/YmdH'). '.log';
        if (strpos($logName, '.') === false) {
            $logName .= '.log';
        }

        $request = $client = $clientHost = '';
        $context = $context ? (is_array($context) ? $context : [$context]) : [];

        $data = [
            'time'         => Time::millisecond(),
            'level'        => $level,
            'service'      => $this->appName,
            'service_host' => $this->host,
            'client'       => $client,
            'client_host'  => $clientHost,
            'request'      => $request,
            'message'      => $message,
            'context'      => $context,
            'name'         => $logName,
        ];

        $this->app->get('log.'. $this->logHandler)->write($data);

        unset($data, $logName, $message, $context, $level);
    }

    /**
     * @param $level
     * @param $params
     */
    public static function __callStatic($level, $params)
    {
        $level = strtolower($level);
        if (! in_array($level, self::$logLevel)) {
            throw new \UnexpectedValueException("Log level [{$level}] does not exist!");
        }

        app('log')->log($level, ...$params);
    }
}
