<?php

namespace Weipaitang\Framework;

use Weipaitan\Framework\Protocol\Protocol;
use Weipaitang\Client\Async\Pool\ManagePool;
use Weipaitang\Config\Config;
use Weipaitang\Config\ConfigInterface;
use Weipaitang\Config\SwooleTableHandler;
use Weipaitang\Framework\ServiceCenter\Register;
use Weipaitang\Log\FileHandler;
use Weipaitang\Log\Log;
use Weipaitang\Log\Logger;
use Weipaitang\Log\RedisHandler;
use Weipaitang\Middleware\MiddlewareTrait;
use Weipaitang\Packet\JsonHandler;
use Weipaitang\Packet\MsgpackHandler;
use Weipaitang\Packet\Packet;
use Weipaitang\Server\Server;
use Weipaitang\Console\Output;
use Weipaitang\Container\Container;
use Swoole\Process as SwooleProcess;
use Swoole\Server as SwooleServer;

/**
 * Class Application
 * @package Weipaitang\Framework
 */
class Application extends Container
{
    /**
     * middleware
     */
    use MiddlewareTrait;

    /**
     * framework version
     */
    const VERSION = '1.0';

    /**
     * @var array
     */
    protected $registeredProviders = [];

    /**
     * @var array
     */
    protected $registeredHooks = [];

    /**
     * Application constructor.
     */
    public function __construct()
    {
        if (php_sapi_name() != "cli") {
            Output::write("The service can only run in command line mode", 'red');
            exit(1);
        }

        self::setInstance($this);

        $this->register('app', $this);
        $this->withBaseComponents();
        $this->initBaseComponents();

        /**
         * @var Config $config
         */
        $config = $this->get('config');

        if (! $config->get('server_ip', '')) {
            $config->set('server_ip', current(swoole_get_local_ip()));
        }

        define('DEBUG_MODEL', $config->get('debug_model', false));

        date_default_timezone_set($config->get('date_default_timezone', 'Asia/Shanghai'));

        register_shutdown_function([$this, 'onShutdown']);
        set_error_handler([$this, 'onError']);
    }

    /**
     * start
     *
     * @return void
     */
    public function start()
    {
        /**
         * @var Config $config
         */
        $config = $this->get('config');

        /**
         * @var Server $server
         */
        $server = $this->get('server');

        $server->withServerName(
            $config->get('server_name', 'Weipaitang')
        );

        $server->withServerSet(
            $config->get('server_set', [])
        );

        $server->withServerConfig(
            $config->get('server_config', [])
        );

        $pidFile = $config->get('server_pidfile', '');
        if ($pidFile) {
            $server->withPidFile(
                storage_path($pidFile)
            );
        }

        $this->registerServerCallback($server);

        $server->run();
    }

    /**
     * @param Server $server
     */
    protected function registerServerCallback(Server $server)
    {
        $protocols = $server->getServerProtocols();
        array_push($protocols, 'task');

        foreach ($protocols as $protocol) {
            $protocol = 'Weipaitan\Framework\Protocol\\'. join('', array_map('ucfirst',
                explode('_', $protocol)
            ));

            /**
             * @var Protocol $protocol
             */
            $protocol = new $protocol;
            $protocol->withServer($server);
            $protocol->withContainer($this);
            $protocol->register();
        }

        $hooks = [
            Server::ON_PIPE_MESSAGE       => 'onPipeMessage',
            Server::HOOK_SERVER_START     => 'onServerStart',
            Server::HOOK_SERVER_STOP      => 'onServerStop',
            Server::HOOK_SERVER_INIT      => 'onServerInit',
            Server::HOOK_WORKER_INIT      => 'onWorkerInit',
            Server::HOOK_TASK_WORKER_INIT => 'onTaskWorkerInit'
        ];

        foreach ($hooks as $name => $hook) {
            $server->withHook($name, $this->getHook($name, [$this, $hook]));
        }
        unset($hooks);
    }


    /**
     * @param SwooleServer $server
     * @param int          $fromId
     * @param string       $message
     */
    public function onPipeMessage(SwooleServer $server, int $fromId, string $message)
    {

    }

    /**
     * on server start
     */
    public function onServerStart()
    {
        if (! $this->get('config')->get('enable_service_center', false)) {
            return;
        }

        $result = (new Register)->register();
        if (! $result or $result['code'] !== 200) {
            Output::write("Register failure.");
            exit(1);
        }

        Output::write("Register success.");
    }

    /**
     * on server stop
     */
    public function onServerStop()
    {
        if (! $this->get('config')->get('enable_service_center', false)) {
            return;
        }

        $result = (new Register)->unregister();
        $status = (! $result or $result['code'] !== 200) ? 'failure' : 'success';

        Output::write("unRegister {$status}.");
    }

    /**
     * @param SwooleServer|null $server
     */
    public function onServerInit(SwooleServer $server = null)
    {
        // init runinfo
        $this->get('runinfo');
    }

    /**
     * @param  SwooleServer|SwooleProcess $worker
     * @param  int                        $workerId
     */
    public function onWorkerInit($worker, int $workerId)
    {
        if ($workerId == 0) {
            $this->get('runinfo')->logMaxQps($worker);
        }

        /**
         * @var ConfigInterface $config
         */
        $config = $this->get('config');

        /**
         * @var ManagePool $pool
         */
        $pool = $this->get('pool');
        $pool->withConfig($config->get('pool'));
        $pool->init();

        $this->register('pool', $pool);

        // log
        $logHandler = strtolower($config->get('log_handler', 'file'));
        if ($logHandler === 'redis') {
            $redis = $pool->select($config->get('log_pool', 'log'));
            if ($redis) {
                /**
                 * @var Logger $logger
                 */
                $logger = Log::getLogger();
                $logger->withLogHandler(
                    (new RedisHandler)->withRedisPool($redis)
                );
            }
        }
    }

    /**
     * @param SwooleServer|SwooleProcess $worker
     * @param int                        $workerId
     */
    public function onTaskWorkerInit($worker, int $workerId)
    {

    }

    /**
     * with base components
     */
    protected function withBaseComponents()
    {
        $components = [
            ['config',    '\Weipaitang\Config\Config',               false],
            ['coroutine', '\Weipaitang\Coroutine\Coroutine',         true],
            ['multi',     '\Weipaitang\Coroutine\Multi',             true],
            ['packet',    '\Weipaitang\Packet\Packet',               false],
            ['server',    '\Weipaitang\Server\Server',               false],
            ['mysql',     '\Weipaitang\Client\Async\Mysql',          true],
            ['redis',     '\Weipaitang\Client\Async\Redis',          true],
            ['tcp',       '\Weipaitang\Client\Async\Tcp',            true],
            ['http',      '\Weipaitang\Client\Async\Http',           true],
            ['dns',       '\Weipaitang\Client\Async\Dns',            true],
            ['file',      '\Weipaitang\Client\Async\File',           true],
            ['lock',      '\Weipaitang\Lock\Lock',                   false],
            ['pool',      'Weipaitang\Client\Async\Pool\ManagePool', false],
            ['runinfo',   '\Weipaitang\Framework\RunInfo',           false],
        ];

        foreach ($components as $item) {
            $this->register($item[0], $item[1], $item[2]);
        }
    }

    /**
     * init base components
     */
    protected function initBaseComponents()
    {
        /**
         * @var Config $config
         */
        $config = $this->get('config');
        $config->withHandler(
            (new SwooleTableHandler)->withPacketHandler(
                new MsgpackHandler
            )
        );

        // log
        $logPath = $config->get('log_path', '');
        $logPath = $logPath ?: 'logs';

        $logger = new Logger;
        $logger->withServer($this->get('server'));
        $logger->withLogHandler(
            (new FileHandler)->withLogPath(
                storage_path($logPath)
            )
        );

        Log::withLogger($logger);

        // package eof
        $packageEof = $config->get('server_set.package_eof');

        // packet handler
        $handler = $config->get('packet_handler', 'msgpack');
        $handler = strtolower($handler) === 'msgpack'
            ? new MsgpackHandler
            : new JsonHandler;

        /**
         * @var Packet $packet
         */
        $packet = $this->get('packet');
        $packet->withPacketHandler($handler);
        if ($packageEof) {
            $packet->withPackageEof($packageEof);
        }
    }

    /**
     * @param string $provider
     * @return $this
     * @throws \Exception
     */
    public function withServiceProvider(string $provider)
    {
        $provider = $this->make($provider);

        if (! $provider instanceof ServiceProvider) {
            throw new \Exception('Service provider must instanceof Weipaitang\Framework\Support\ServiceProvider');
        }

        $providerName = get_class($provider);
        if (! isset($this->registeredProviders[$providerName])) {
            $this->registeredProviders[$providerName] = true;

            $provider->handler();
        }

        return $this;
    }

    /**
     * @param  int    $name
     * @param  mixed  $callback
     * @return $this
     */
    public function withHook(int $name, $callback)
    {
        $this->registeredHooks[$name] = $callback;

        return $this;
    }

    /**
     * @param  int    $name
     * @param  array  $default
     * @return array|callable
     */
    public function getHook(int $name, array $default)
    {
        return $this->registeredHooks[$name] ?? $default;
    }

    /**
     * @param $errNo
     * @param $errStr
     * @param $errFile
     * @param $errLine
     * @param $errContext
     */
    public function onError($errNo, $errStr, $errFile, $errLine, $errContext)
    {
        Log::error(
            "服务错误: {$errStr}",
            [
                $errNo,
                "{$errFile}:{$errLine}",
                $errStr,
                $errContext,
            ]
        );
    }

    /**
     * on shutdown
     */
    public function onShutdown()
    {
        $error = error_get_last();
        if (isset($error['type']) and in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $message = $error['message'];
            $file = $error['file'];
            $line = $error['line'];

            $log = [
                "WORKER EXIT UNEXPECTED",
                "$message ($file:$line)",
                "Stack trace:",
            ];

            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            foreach ($trace as $i => $t) {
                $t['file'] = $t['file'] ?? 'unknown';
                $t['line'] = $t['line'] ?? 0;
                $t['function'] = $t['function'] ?? 'unknown';

                $logString = "#$i {$t['file']} ({$t['line']}): ";

                if (isset($t['object']) and is_object($t['object'])) {
                    $logString .= get_class($t['object']) . '->';
                }

                $logString .= "{$t['function']}()";

                $log[] = $logString;
            }

            Log::error('服务崩溃', $log);
        }
        unset($error);
    }
}
