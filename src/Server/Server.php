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
    protected $server;

    /**
     * Server name
     *
     * @var string
     */
    protected $serverName;

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
        $this->config['daemonize'] = $this->app['command']->run() ? 1 : 0;

        // 启用 HTTP 服务
        if ($this->app['config']->get('enable_http_server', false)) {
            $httpServerIp = $this->app['config']->get('http_server_ip', '127.0.0.1');
            $httpServerPort = $this->app['config']->get('http_server_port', 9501);

            $this->startHttpServer($httpServerIp, (int)$httpServerPort);

            Console::write("Start http server, port: {$httpServerPort}");
        }

        // 启用 TCP 服务
        if ($this->app['config']->get('enable_tcp_server', false)) {
            $tcpServerIp = $this->app['config']->get('tcp_server_ip', '127.0.0.1');
            $tcpServerPort = $this->app['config']->get('tcp_server_port', 9501);

            $this->startTcpServer($tcpServerIp, (int)$tcpServerPort);

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
     * @param int          $workerId
     */
    public function onWorkerStart(SwooleServer $server, int $workerId)
    {
        $title = 'Worker|';
        $hook  = Define::HOOK_WORKER_INIT;

        if ($server->taskworker) {
            $title = 'Task|';
            $hook  = Define::HOOK_TASK_INIT;
        }

        Console::setProcessTitle($title. $workerId);

        // 初始化Worker/Task进程
        $this->hook($hook, $server, $workerId);
    }

    /**
     * @param SwooleServer $server
     * @param int          $workerId
     * @param int          $workerPid
     * @param int          $exitCode
     * @param int          $signal
     */
    public function onWorkerError(SwooleServer $server, int $workerId, int $workerPid, int $exitCode, int $signal)
    {
        Log::emergency('worker error', [
            'worker_id'  => $workerId,
            'worker_pid' => $workerPid,
            'exit_code'  => $exitCode,
            'signal'     => $signal
        ]);
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
     * @param int          $taskId
     * @param int          $workerId
     * @param mixed        $data
     */
    public function onTask(SwooleServer $server, int $taskId, int $workerId, $data)
    {
        Console::setProcessTitle("Task|{$taskId}|{$workerId}");

        $this->app->get('dispatcher.task')->dispatch($data);
    }

    /**
     * @param SwooleServer $server
     * @param int          $taskId
     * @param string       $data
     */
    public function onFinish(SwooleServer $server, int $taskId, string $data)
    {
        $this->hook(Define::ON_TASK_FINISH, $server, $taskId, $data);
    }

    /**
     * @param SwooleServer $server
     * @param int          $fromId
     * @param string       $message
     */
    public function onPipeMessage(SwooleServer $server, int $fromId, string $message)
    {
        $this->hook(Define::ON_PIPE_MESSAGE, $server, $fromId, $message);
    }

    /**
     * @param SwooleServer $server
     * @param int          $workerId
     */
    public function onWorkerStop(SwooleServer $server, int $workerId)
    {
    }

    /**
     * @param SwooleServer $server
     */
    public function onManagerStop(SwooleServer $server)
    {
    }

    /**
     * @param string $httpServerIp
     * @param int    $httpServerPort
     */
    public function startHttpServer(string $httpServerIp, int $httpServerPort)
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

        try {
            $this->app->get('dispatcher.http')->dispatch(
                $this->app->get('request', $request),
                $response
            );
        } catch (\Exception $e) {
            $response->status($e->getCode() ?: 500);
            $response->end('Server Error : ' . $e->getMessage());
        }
    }

    /**
     * @param string $tcpServerIp
     * @param int    $tcpServerPort
     */
    public function startTcpServer(string $tcpServerIp, int $tcpServerPort)
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
     * @param int          $fd
     * @param int          $fromId
     * @param string       $data
     */
    public function onReceive(SwooleServer $server, int $fd, int $fromId, string $data)
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
     * @param int          $fd
     * @param int          $fromId
     */
    public function onClose(SwooleServer $server, int $fd, int $fromId)
    {
    }

    /**
     * @param SwooleServer $server
     * @param int          $fd
     * @param int          $fromId
     */
    public function onConnect(SwooleServer $server, int $fd, int $fromId)
    {
    }

    /**
     * @param int   $fd
     * @param mixed $data
     * @param int   $code
     */
    public function send(int $fd, $data, int $code = 200)
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
     * @param int   $fd
     * @param mixed $data
     * @param bool  $isEnd
     * @param int   $code
     */
    public function batchSend(int $fd, $data, bool $isEnd = false, int $code = 200)
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
