<?php

namespace Weipaitang\Framework;

use Weipaitang\Framework\Protocol\Protocol;
use Weipaitang\Client\Async\AbstractAsync;
use Weipaitang\Client\Async\Pool\ManagePool;
use Weipaitang\Client\Async\Pool\MySQLPool;
use Weipaitang\Client\Async\Pool\RedisMultiPool;
use Weipaitang\Client\Async\Pool\RedisPool;
use Weipaitang\Client\Async\Pool\TcpPool;
use Weipaitang\Config\Config;
use Weipaitang\Config\ConfigInterface;
use Weipaitang\Config\SwooleTableHandler;
use Weipaitang\Log\FileHandler;
use Weipaitang\Log\Log;
use Weipaitang\Log\Logger;
use Weipaitang\Log\RedisHandler;
use Weipaitang\Middleware\MiddlewareTrait;
use Weipaitang\Packet\JsonHandler;
use Weipaitang\Packet\MsgpackHandler;
use Weipaitang\Packet\Packet;
use Weipaitang\Pool\AbstractPool;
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

        $serverSet = $config->get('server_set', []);
        $serverSet['worker_num']      = max(1, $serverSet['worker_num'] ?? 1);
        $serverSet['task_worker_num'] = max(1, $serverSet['task_worker_num'] ?? 1);

        // server set
        $server->withServerSet($serverSet);

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
            $protocol = 'Weipaitang\Framework\Protocol\\'. join('', array_map('ucfirst',
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
            Server::HOOK_SERVER_START     => 'onServerStart',
            Server::HOOK_SERVER_STOP      => 'onServerStop',
            Server::ON_PIPE_MESSAGE       => 'onPipeMessage',
            Server::HOOK_SERVER_INIT      => 'onServerInit',
            Server::HOOK_WORKER_INIT      => 'onWorkerInit',
            Server::HOOK_TASK_WORKER_INIT => 'onTaskWorkerInit'
        ];

        foreach ($hooks as $name => $hook) {
            $server->withHook($name, [$this, $hook]);
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
        $this->hook(Server::ON_PIPE_MESSAGE, $server, $fromId, $message);
    }

    /**
     * on server start
     */
    public function onServerStart()
    {
        $this->hook(Server::HOOK_SERVER_START, $this);

        if (! $this->get('config')->get('enable_service_center', false)) {
            return;
        }

        $result = $this->get('register')->register();
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
        $this->hook(Server::HOOK_SERVER_STOP, $this);

        if (! $this->get('config')->get('enable_service_center', false)) {
            return;
        }

        $result = $this->get('register')->unregister();
        $status = (! $result or $result['code'] !== 200) ? 'failure' : 'success';

        Output::write("unRegister {$status}.");
    }

    /**
     * @param Server|null $server
     */
    public function onServerInit(Server $server = null)
    {
        // init runinfo
        $this->get('runinfo');

        $this->hook(Server::HOOK_SERVER_INIT, $server);
    }

    /**
     * @param  SwooleServer|SwooleProcess $worker
     * @param  int                        $workerId
     */
    public function onWorkerInit($worker, int $workerId)
    {
        $this->hook(Server::HOOK_WORKER_INIT, $worker, $workerId);

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
        $poolConfig = $config->get('pool');
        foreach ($poolConfig as &$v) {
            $v['pool_hooks'] = $v['pool_hooks'] ?? $v['pool_hooks'] = [
                AbstractPool::WARNING_ABOVE_MAX_SIZE => function ($pool) {
                    /**
                     * @var MySQLPool|RedisPool|RedisMultiPool|TcpPool $pool
                     */
                    Log::error('The number of connections exceeds the maximum.', $pool->getDebugInfo());
                }
            ];

            $v['hooks'] = $v['hooks'] ?? [
                AbstractAsync::HOOK_WARNING_EXEC_TIMEOUT => function (AbstractAsync $client, $runTime) {
                    Log::error('Execute timeout. time: '. $runTime, $client->getDebugInfo());
                },
                AbstractAsync::HOOK_EXEC_ERROR           => function (AbstractAsync $client, string $error, $errno) {
                    Log::error($error. " ({$errno})", $client->getDebugInfo());
                },
            ];
        }

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
        $this->hook(Server::HOOK_TASK_WORKER_INIT, $worker, $workerId);
    }

    /**
     * with base components
     */
    protected function withBaseComponents()
    {
        $components = [
            ['config',    '\Weipaitang\Config\Config',                    true],
            ['coroutine', '\Weipaitang\Coroutine\Coroutine',              false],
            ['multi',     '\Weipaitang\Coroutine\Multi',                  false],
            ['packet',    '\Weipaitang\Packet\Packet',                    true],
            ['server',    '\Weipaitang\Server\Server',                    true],
            ['mysql',     '\Weipaitang\Client\Async\Mysql',               false],
            ['redis',     '\Weipaitang\Client\Async\Redis',               false],
            ['tcp',       '\Weipaitang\Client\Async\Tcp',                 false],
            ['http',      '\Weipaitang\Client\Async\Http',                false],
            ['dns',       '\Weipaitang\Client\Async\Dns',                 false],
            ['file',      '\Weipaitang\Client\Async\File',                false],
            ['lock',      '\Weipaitang\Lock\Lock',                        true],
            ['pool',      '\Weipaitang\Client\Async\Pool\ManagePool',     true],
            ['runinfo',   '\Weipaitang\Framework\RunInfo',                true],
            ['manage',    '\Weipaitang\Framework\ServiceCenter\Manage',   false],
            ['register',  '\Weipaitang\Framework\ServiceCenter\Register', false],
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
        $config->withPath(root_path('config'));
        $config->init();

        // log
        $logger = new Logger;
        $logger->withServer($this->get('server'));
        $logger->withLogHandler(
            (new FileHandler)->withLogPath(
                storage_path($config->get('log_path', 'logs'))
            )
        );

        Log::withLogger($logger);

        // package eof
        $packageEof = $config->get('server_set')['package_eof'];

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
     * @param int   $hook
     * @param array ...$arguments
     */
    public function hook(int $hook, ...$arguments)
    {
        if (! isset($this->registeredHooks[$hook])) {
            return;
        }

        call_user_func_array($this->registeredHooks[$hook], $arguments);
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
