<?php

namespace Flower\Log;

use Flower\Core\Application;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Flower\Contract\LogHandler;
use Flower\Utility\Time;

/**
 * Class Logger
 *
 * @package Flower\Core
 */
class Logger extends AbstractLogger
{
    /**
     * @var string
     */
    private $appName;

    /**
     * @var string
     */
    private $logName;

    /**
     * @var LogHandler
     */
    private $logHandler;

    /**
     * Logger constructor.
     *
     * @param Application $app
     * @param string|null $logName
     */
    public function __construct(Application $app, string $logName = null)
    {
        $this->logName = $logName;
        $this->appName = $app['server']->getServerName();
        $this->host    = $app['config']->get('server_ip', '127.0.0.1')
            . '_'
            . $app['config']->get('tcp_server_port', '9501');

        $this->logHandler = $app->get('log.' . $app['config']->get('log_handler', 'file'));
    }

    /**
     * @param string       $level
     * @param string|array $message
     * @param array        $context
     */
    public function log($level, $message, array $context = [])
    {
        $logName = $this->logName ?: date('Ym/YmdH') . '.log';
        if (strpos($logName, '.') === false) {
            $logName .= '.log';
        }

        $request = $client = $clientHost = '';
        $context = $context ? (is_array($context) ? $context : [$context]) : [];

        $this->logHandler->write([
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
        ]);

        unset($logName, $message, $context, $level);
    }
}
