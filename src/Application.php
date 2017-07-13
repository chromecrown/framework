<?php

namespace Weipaitang\Framework;

use Weipaitan\Framework\Protocol\Protocol;
use Weipaitang\Config\Config;
use Weipaitang\Config\SwooleTableHandler;
use Weipaitang\Coroutine\Coroutine;
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
    use MiddlewareTrait;

    const VERSION = '1.0';

    /**
     * @var array
     */
    protected $registerProviders = [];

    /**
     * Application constructor.
     */
    public function __construct()
    {
        if (php_sapi_name() != "cli") {
            Output::write("只能运行在命令行模式下", 'red');
            exit(1);
        }

        self::setInstance($this);

        $this->register('app', $this);
        $this->registerBaseComponents();

        /**
         * @var Config $config
         */
        $config = $this->get('config');

        if (! $config->get('server_ip', '')) {
            $config->set('server_ip', current(swoole_get_local_ip()));
        }

        define('DEBUG_MODEL', $config->get('debug_model', false));

        date_default_timezone_set($config->get('default_timezone', 'Asia/Shanghai'));

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

        $server->withHook(Server::ON_PIPE_MESSAGE, [$this, 'onPipeMessage']);

        $server->withHook(Server::HOOK_SERVER_START, [$this, 'onServerStart']);
        $server->withHook(Server::HOOK_SERVER_STOP, [$this, 'onServerStop']);

        $server->withHook(Server::HOOK_SERVER_INIT, [$this, 'onServerInit']);

        $server->withHook(Server::HOOK_WORKER_INIT, [$this, 'onWorkerInit']);
        $server->withHook(Server::HOOK_TASK_WORKER_INIT, [$this, 'onTaskWorkerInit']);
    }

    /**
     * @param SwooleServer $server
     * @param              $fromId
     * @param              $message
     */
    public function onPipeMessage(SwooleServer $server, $fromId, $message)
    {

    }

    /**
     * on server start
     */
    public function onServerStart()
    {
        $enableManageCenter = $this->get('config')->get('enable_manage_center', false);
        if (! $enableManageCenter) {
            return;
        }

        // fixme new register
        $result = $this->get('manage.register')->register();
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
        $enableManageCenter = $this->get('config')->get('enable_manage_center', false);
        if (! $enableManageCenter) {
            return;
        }

        // fixme new register
        $result = $this->get('manage.register')->unregister();
        $status = (! $result or $result['code'] !== 200) ? 'failure' : 'success';

        Output::write("unRegister {$status}.");
    }

    /**
     * @param SwooleServer|null $server
     */
    public function onServerInit(SwooleServer $server = null)
    {
        $this->register('runinfo', new RunInfo, true);
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

//        $pool = new Pool;
//        $pool->withContainer($this);
//        $pool->withWorkerId($workerId);
//        $pool->initPool();
    }

    /**
     * @param SwooleServer|SwooleProcess $worker
     * @param int                        $workerId
     */
    public function onTaskWorkerInit($worker, int $workerId)
    {

    }

    /**
     * 把框架组件注册到容器
     *
     * register base components
     */
    protected function registerBaseComponents()
    {
        $this->registerConfigComponent();
        $this->registerLogComponent();
        $this->registerDatabaseComponent();
        $this->registerCoroutineComponent();
        $this->registerPacketComponent();
        $this->registerPoolComponent();
        $this->registerClientComponent();
        $this->registerServerComponent();
    }

    private function registerConfigComponent()
    {
        $this->register('config', Config::class, true);

        /**
         * @var Config $config
         */
        $config = $this->get('config');
        $config->withHandler(
            (new SwooleTableHandler)->withPacketHandler(
                new MsgpackHandler
            )
        );
    }

    private function registerLogComponent()
    {

    }

    private function registerDatabaseComponent()
    {

    }

    private function registerCoroutineComponent()
    {
        $this->register('coroutine', Coroutine::class);
    }

    private function registerPacketComponent()
    {
        $this->register('packet', Packet::class, true);

        /**
         * @var Config $config
         */
        $config = $this->get('config');

        // package eof
        $packageEof = $config->get('server_set.package_eof');

        // packet handler
        $handler = $config->get('packet_handler', 'msgpack');
        $handler = strtolower($handler);
        $handler = $handler === 'msgpack' ? new MsgpackHandler : new JsonHandler;

        /**
         * @var Packet $packet
         */
        $packet = $this->get('packet');
        $packet->withPacketHandler($handler);
        if ($packageEof) {
            $packet->withPackageEof($packageEof);
        }
    }

    private function registerPoolComponent()
    {

    }

    private function registerClientComponent()
    {

    }

    private function registerServerComponent()
    {
        $this->register('server', Server::class, true);
    }

    /**
     * @param string $provider
     * @throws \Exception
     */
    public function withServiceProvider(string $provider)
    {
        $provider = $this->make($provider);

        if (! $provider instanceof ServiceProvider) {
            throw new \Exception('Provider must instanceof Weipaitang\Framework\Support\ServiceProvider');
        }

        $providerName = get_class($provider);
        if (isset($this->registerProviders[$providerName])) {
            return;
        }

        $this->registerProviders[$providerName] = true;

        $provider->handler();
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
//        Log::error(
//            "服务错误: {$errStr}",
//            [
//                $errNo,
//                "{$errFile}:{$errLine}",
//                $errStr,
//                $errContext,
//            ]
//        );
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

            $trace = debug_backtrace();
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

//            Log::error('服务崩溃', $log);
        }
        unset($error);
    }
}