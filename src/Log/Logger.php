<?php

namespace Wpt\Framework\Log;

use Wpt\Framework\Core\Application;
use Wpt\Framework\Utility\Console;
use Psr\Log\AbstractLogger;
use Wpt\Framework\Utility\Time;

/**
 * Class Logger
 *
 * @package Wpt\Framework\Core
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
     * @var LogHandlerInterface
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

        $context = $context ? (is_array($context) ? $context : [$context]) : [];

        $this->logHandler->write([
            'time'         => Time::millisecond(),
            'level'        => $level,
            'service'      => $this->appName,
            'service_host' => $this->host,
            'message'      => $message,
            'context'      => $context,
            'name'         => $logName,
        ]);

        if (DEBUG_MODEL) {
            Console::debug($message. " ". json_encode($context, JSON_UNESCAPED_UNICODE));
        }

        unset($logName, $message, $context, $level);
    }
}
