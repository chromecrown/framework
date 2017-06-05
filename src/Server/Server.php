<?php

namespace Flower\Server;

use Flower\Log\Log;
use Flower\Utility\File;
use Flower\Support\Define;
use Flower\Utility\Console;
use Flower\Core\Application;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server as SwooleServer;
use Swoole\Http\Server as SwooleHttpServer;

/**
 * Class Server
 *
 * @package Flower\Server
 */
class Server
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * Swoole server instance
     *
     * @var SwooleServer
     */
    protected $server = null;

    /**
     * Server name
     *
     * @var null|string
     */
    protected $serverName = 'Flower';

    /**
     * Swoole server pid file path
     *
     * @var string
     */
    protected $pidFile;

    /**
     * Swoole server config
     *
     * @var array
     */
    protected $config = [
        'open_eof_check' => 1,
        'open_eof_split' => 1,
        'package_eof'    => "#\r\n\r\n",

        'pipe_buffer_size' => 1024 * 1024 * 64,

        'open_tcp_nodelay'  => 1,
        'open_cpu_affinity' => 1,

        'heartbeat_idle_time'      => 30,
        'heartbeat_check_interval' => 30,

        'reactor_num'     => 96,
        'worker_num'      => 96,
        'task_worker_num' => 0,

        'max_request'      => 0,
        'task_max_request' => 0,

        'backlog'     => 50000,
        'log_level'   => 1,
        'log_file'    => '/server.log',
        'task_tmpdir' => '/tmp/task/',

        'daemonize' => 0,
    ];

    /**
     * @var array
     */
    protected $hook = [];

    /**
     * @var array
     */
    protected $allowClientIpList;

    /**
     * Server constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        // 获取当前服务名
        $this->serverName = ucfirst($this->app['config']->get('server_name', 'Flower'));

        // set config
        $this->config = array_merge($this->config, $this->app['config']->get('server_config', []));

        // 存储地址
        $this->config['log_file'] = storage_path($this->config['log_file']);
        $this->config['task_tmpdir'] = storage_path($this->config['task_tmpdir']);

        // pid 文件路径
        $this->pidFile = storage_path('/' . $this->serverName . '.pid');

        // 运行客户端连接的IP列表
        if ($this->app['config']->get('check_remote_ip', false)) {
            array_map(function ($v) {
                $this->allowClientIpList[$v] = true;
            }, $this->app['config']->get('allow_client_ip', ['127.0.0.1']));
        }
    }

    /**
     * start swoole server
     */
    public function start()
    {
        if ($isDaemon = $this->app['command']->run()) {
            $this->config['daemonize'] = true;
        }

        // 启用 HTTP 服务
        if ($this->app['config']->get('enable_http_server', false)) {
            $httpServerIp = $this->app['config']->get('http_server_ip', '127.0.0.1');
            $httpServerPort = $this->app['config']->get('http_server_port', '9501');

            $this->startHttpServer($httpServerIp, $httpServerPort);

            Console::write("Start http server, por t: {$httpServerPort}");
        }

        // 启用 TCP 服务
        if ($this->app['config']->get('enable_tcp_server', false)) {
            $tcpServerIp = $this->app['config']->get('tcp_server_ip', '127.0.0.1');
            $tcpServerPort = $this->app['config']->get('tcp_server_port', '9501');

            $this->startTcpServer($tcpServerIp, $tcpServerPort);

            Console::write("Start tcp server, port: {$tcpServerPort}");
        }

        // 修改进程名
        Console::setProcessTitle();

        $this->server->on('Start', [$this, 'onStart']);
        $this->server->on('ManagerStart', [$this, 'onManagerStart']);
        $this->server->on('ManagerStop', [$this, 'onManagerStop']);
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->server->on('WorkerStop', [$this, 'onWorkerStop']);
        $this->server->on('WorkerError', [$this, 'onWorkerError']);
        $this->server->on('Task', [$this, 'onTask']);
        $this->server->on('Finish', [$this, 'onFinish']);
        $this->server->on('PipeMessage', [$this, 'onPipeMessage']);

        // 初始化服务
        $this->hook(Define::HOOK_SERVER_INIT, $this->server);

        $this->server->start();
    }

    /**
     * @param SwooleServer $server
     */
    public function onStart(SwooleServer $server)
    {
        Console::setProcessTitle("Master");

        Console::write("MasterPid={$server->master_pid}");
        Console::write("ManagerPid={$server->manager_pid}");
        Console::write("Server: start. Swoole version is [" . SWOOLE_VERSION . "]");

        // 写入 swoole server pid
        File::write($this->pidFile, $server->master_pid . ',' . $server->manager_pid);
    }

    /**
     * @param SwooleServer $server
     * @param              $workerId
     */
    public function onWorkerStart(SwooleServer $server, $workerId)
    {
        if ($server->taskworker) {
            Console::setProcessTitle("Task|{$workerId}");

            // 初始化 task 进程
            $this->hook(Define::HOOK_TASK_INIT, $server, $workerId);
        } else {
            Console::setProcessTitle("Worker|{$workerId}");

            // 初始化 Worker 进程
            $this->hook(Define::HOOK_WORKER_INIT, $server, $workerId);
        }
    }

    /**
     * @param SwooleServer $server
     * @param              $workerId
     * @param              $workerPid
     * @param              $exitCode
     */
    public function onWorkerError(SwooleServer $server, $workerId, $workerPid, $exitCode)
    {
        Log::emergency('worker error', [$workerId, $workerPid, $exitCode]);
    }

    /**
     * @param SwooleServer $server
     */
    public function onManagerStart(SwooleServer $server)
    {
        Console::setProcessTitle("Manager");
    }

    /**
     * @param SwooleServer $server
     * @param              $taskId
     * @param              $workerId
     * @param              $data
     * @throws \Exception
     */
    public function onTask(SwooleServer $server, $taskId, $workerId, $data)
    {
        Console::setProcessTitle("Task|{$taskId}|{$workerId}");

        $this->app->get('dispatcher.task')->dispatch($data);
    }

    /**
     * @param SwooleServer $server
     * @param              $taskId
     * @param              $data
     */
    public function onFinish(SwooleServer $server, $taskId, $data)
    {
        $this->hook(Define::ON_TASK_FINISH, $server, $taskId, $data);
    }

    /**
     * @param SwooleServer $server
     * @param              $fromId
     * @param              $message
     */
    public function onPipeMessage(SwooleServer $server, $fromId, $message)
    {
        $this->hook(Define::ON_PIPE_MESSAGE, $server, $fromId, $message);
    }

    /**
     * @param SwooleServer $server
     * @param              $workerId
     */
    public function onWorkerStop(SwooleServer $server, $workerId)
    {
    }

    /**
     * @param SwooleServer $server
     */
    public function onManagerStop(SwooleServer $server)
    {
    }

    /**
     * @param $httpServerIp
     * @param $httpServerPort
     */
    public function startHttpServer($httpServerIp, $httpServerPort)
    {
        $this->server = new SwooleHttpServer($httpServerIp, $httpServerPort);

        $this->server->on('request', [$this, 'onRequest']);
        $this->server->set($this->config);
    }

    /**
     * on swoole server receive http request
     *
     * @param Request  $request
     * @param Response $response
     */
    public function onRequest(Request $request, Response $response)
    {
        if ($request->server['request_uri'] == '/favicon.ico') {
            $response->end();

            return;
        }

        if (! isset($request->get)) {
            $request->get = [];
        }

        if (! isset($request->post)) {
            $request->post = [];
        }

        // merge
        $request->ip = $this->getClientIp($request->server);
        $request->request = array_merge($request->get, $request->post);

        try {
            $this->app->get('dispatcher.http')->dispatch($request, $response);
        } catch (\Exception $e) {
            $response->status(500);
            $response->end('Server Error : ' . $e->getMessage());
        }
    }

    /**
     * @param $server
     * @return string
     */
    public function getClientIp($server)
    {
        if (isset($server['x-real-ip']) and strcasecmp($server['x-real-ip'], 'unknown')) {
            return $server['x-real-ip'];
        }

        if (isset($server['client_ip']) and strcasecmp($server['client_ip'], 'unknown')) {
            return $server['client_ip'];
        }

        if (isset($server['x_forwarded_for']) and strcasecmp($server['x_forwarded_for'], 'unknown')) {
            return $server['x_forwarded_for'];
        }

        if (isset($server['remote_addr'])) {
            return $server['remote_addr'];
        }

        return '';
    }

    /**
     * @param $tcpServerIp
     * @param $tcpServerPort
     */
    public function startTcpServer($tcpServerIp, $tcpServerPort)
    {
        // 如果已经启动了 HTTP 服务，只需要添加监听就行了
        if ($this->server != null) {
            $tcpServer = $this->server->addListener($tcpServerIp, $tcpServerPort, SWOOLE_SOCK_TCP);

            $tcpServer->set($this->config);

            $tcpServer->on('Connect', [$this, 'onConnect']);
            $tcpServer->on('Receive', [$this, 'onReceive']);
            $tcpServer->on('Close', [$this, 'onClose']);

            return;
        }

        // 木有启动 HTTP 服务，那就启动 TCP Server
        $this->server = new SwooleServer($tcpServerIp, $tcpServerPort, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);

        $this->server->on('Connect', [$this, 'onConnect']);
        $this->server->on('Receive', [$this, 'onReceive']);
        $this->server->on('Close', [$this, 'onClose']);

        $this->server->set($this->config);
    }

    /**
     * @param SwooleServer $server
     * @param              $fd
     * @param              $fromId
     * @param              $data
     */
    public function onReceive(SwooleServer $server, $fd, $fromId, $data)
    {
        if ($this->allowClientIpList) {
            $ip = $server->connection_info($fd)['remote_ip'];
            if (! isset($this->allowClientIpList[$ip])) {
                $this->send($fd, null, 404);

                return;
            }
        }

        $this->app->get('dispatcher.tcp')->dispatch($server, $fd, $fromId, $data);
    }

    /**
     * @param SwooleServer $server
     * @param              $fd
     * @param              $fromId
     */
    public function onClose(SwooleServer $server, $fd, $fromId)
    {
    }

    /**
     * @param SwooleServer $server
     * @param              $fd
     * @param              $fromId
     */
    public function onConnect(SwooleServer $server, $fd, $fromId)
    {
    }

    /**
     * @param     $fd
     * @param     $data
     * @param int $code
     */
    public function send($fd, $data, int $code = 200)
    {
        if (! $this->server->exist($fd)) {
            return;
        }

        $packet = $this->app->get('packet');
        $data = $packet->encode($packet->format($data, $code));

        if (mb_strlen($data) > 1024 * 1024) {
            $data = str_split($data, 1024 * 1024);
        } else {
            $data = [$data];
        }

        foreach ($data as $v) {
            $this->server->send($fd, $v);
        }
    }

    /**
     * 批量发送 (在数据量大时应用)
     *
     * @param      $fd
     * @param      $data
     * @param bool $isEnd
     * @param int  $code
     */
    public function batchSend($fd, $data, bool $isEnd = false, int $code = 200)
    {
        if (! $this->server->exist($fd)) {
            return;
        }

        $packet = $this->app->get('packet');
        $data = $packet->encode($packet->format($data, $code), $packet->getSplitEof());

        if (mb_strlen($data) > 1024 * 1024) {
            $data = str_split($data, 1024 * 1024);
        } else {
            $data = [$data];
        }

        foreach ($data as $v) {
            $this->server->send($fd, $v);
        }

        if ($isEnd) {
            $this->server->send($fd, $packet->getPackageEof());
        }
    }

    /**
     * 获取 Swoole 服务实例
     *
     * @return SwooleServer
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param string $name
     * @param array  $callback
     */
    public function setHook(string $name, array $callback)
    {
        $this->hook[$name] = $callback;
    }

    /**
     * 获取服务名
     *
     * @return null|string
     */
    public function getServerName()
    {
        return $this->serverName;
    }

    /**
     * @return string
     */
    public function getPidFile()
    {
        return $this->pidFile;
    }

    /**
     * @param string $name
     * @param array  ...$arguments
     */
    public function hook(string $name, ... $arguments)
    {
        if (! isset($this->hook[$name])) {
            return;
        }

        call_user_func_array($this->hook[$name], $arguments);
    }

    /**
     * 析构函数，关闭 server
     */
    public function __destruct()
    {
        if ($this->server) {
            Console::write("Server Was Shutdown...");
        }
    }
}
