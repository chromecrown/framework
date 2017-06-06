<?php

namespace Flower\Core;

use Flower\Log\Log;
use Flower\Server\Server;
use Flower\Utility\Console;
use Flower\Support\Define;
use Flower\Support\ServiceProvider;
use Swoole\Process as SwooleProcess;
use Swoole\Server as SwooleServer;
use Swoole\Table as SwooleTable;

/**
 * Class Application
 *
 * @package Flower\Core
 */
class Application extends Container
{
    /**
     * 全局运行状态表，记录请求数，响应时间等
     *
     * @var SwooleTable
     */
    protected $runTable;

    /**
     * 全局配置表，配置表中的数据可以在运行时动态更改
     *
     *     hasConfig()
     *     getConfig()
     *     setConfig()
     *     delConfig()
     *
     * @var SwooleTable
     */
    protected $configTable;

    /**
     * 注册的服务列表
     *
     * @var array
     */
    protected $registerProviders = [];

    /**
     * Application constructor.
     */
    public function __construct()
    {
        // 检查是否运行在cli模式
        if (php_sapi_name() != "cli") {
            Console::write("只能运行在命令行模式下", 'red');
            exit(1);
        }

        // 绑定唯一实例到容器
        self::setInstance($this);

        // 绑定到容器
        $this->bind('app', $this);
        $this->alias('app', 'Flower\Core\Application');
        $this->registerBindings();

        // 如果没有设置 server_ip，则获取当前服务器IP （服务器第一块网卡）
        if (! $this['config']->get('server_ip', '')) {
            $this['config']->set('server_ip', current(swoole_get_local_ip()));
        }

        // 定义一个空值常量
        define('nil', 'nil', true);

        // 设置是否DEBUG
        define('DEBUG_MODEL', $this['config']->get('debug_model', false));

        // 设置时区
        date_default_timezone_set($this['config']->get('default_timezone', 'Asia/Shanghai'));

        // 设置运行结束执行函数
        register_shutdown_function([$this, 'onShutdown']);

        // 设置系统错误执行函数
        set_error_handler([$this, 'onError']);
    }

    /**
     * 向框架注册服务
     *
     * @param string $provider
     * @throws \Exception
     */
    public function register(string $provider)
    {
        // 实例化服务
        $provider = $this->make($provider);

        if (! ($provider instanceof ServiceProvider)) {
            throw new \Exception('Provider must instanceof Flower\Support\ServiceProvider');
        }

        $providerName = get_class($provider);
        if (isset($this->registerProviders[$providerName])) {
            return;
        }

        $this->registerProviders[$providerName] = true;

        // 执行注册方法
        if (method_exists($provider, 'register')) {
            $provider->register();
        }
    }

    /**
     * 把框架组件注册到容器
     *
     * register bindings
     */
    private function registerBindings()
    {
        foreach (Define::BINDINGS as $k => $v) {
            if (! is_array($v)) {
                $v = [$v, true];
            }

            $this->bind($k, $v[0], $v[1]);
        }
    }

    /**
     * 启动服务
     *
     * @var Server;
     */
    public function start()
    {
        // 实例化服务
        $server = $this->get('server');

        // 启用 Tcp 服务器并且启用了服务中心，则向服务中心注册
        if ($this['config']->get('enable_tcp_server', false)
            and $this['config']->get('enable_services_center', false)
        ) {
            $server->setHook(Define::HOOK_SERVER_START, [$this, 'onServerStart']);
            $server->setHook(Define::HOOK_SERVER_STOP, [$this, 'onServerStop']);
        }

        $server->setHook(Define::HOOK_SERVER_INIT, [$this, 'onServerInit']);
        $server->setHook(Define::HOOK_WORKER_INIT, [$this, 'onWorkerInit']);
        $server->setHook(Define::ON_PIPE_MESSAGE, [$this, 'onPipeMessage']);

        // 启动服务
        $server->start();
    }

    /**
     * 向服务中心注册
     */
    public function onServerStart()
    {
        $result = $this->get('manage.register')->register();
        if (! $result or $result['code'] !== 200) {
            Console::write("Register failure.");
            exit(1);
        }

        Console::write("Register success.");
    }

    /**
     * 向服务中心反注册
     */
    public function onServerStop()
    {
        $result = $this->get('manage.register')->unregister();
        $status = (! $result or $result['code'] !== 200) ? 'failure' : 'success';

        Console::write("unRegister {$status}.");
    }

    /**
     * 当 Server 管道收到消息
     *
     * @param SwooleServer $server
     * @param              $fromId
     * @param              $message
     */
    public function onPipeMessage(SwooleServer $server, $fromId, $message)
    {

    }

    /**
     * 当 Server 初始化
     *
     * @param \Swoole\Server $server
     */
    public function onServerInit(SwooleServer $server = null)
    {
        // 初始化全局运行状态表
        $table = new SwooleTable(50);
        $table->column('success', SwooleTable::TYPE_INT, 8);
        $table->column('failure', SwooleTable::TYPE_INT, 8);
        $table->column('time', SwooleTable::TYPE_FLOAT, 8);
        $table->create();
        $this->runTable = $table;

        // 初始化全局动态配置表
        $configTable = new SwooleTable($this['config']->get('config_table_row', 1024));
        $configTable->column('value', SwooleTable::TYPE_STRING, 1024);
        $configTable->create();
        $this->configTable = $configTable;

        // 把配置文件中的全局配置加载到全局内存配置表
        $globalConfig = $this['config']->get('global', []);
        if ($globalConfig) {
            foreach ($globalConfig as $k => $v) {
                $this->setConfig($k, $v);
            }
        }
    }

    /**
     * 获取全局运行状态表资源
     *
     * @return SwooleTable
     */
    public function getRunTable()
    {
        return $this->runTable;
    }

    /**
     * 当 Worker 初始化
     *
     * @param  SwooleServer|SwooleProcess $worker
     * @param  int                        $workerId
     * @throws \Exception
     */
    public function onWorkerInit($worker, int $workerId)
    {
        // 记录请求 QPS
        if ($workerId == 0) {
            $this->logMaxQps($worker);
        }

        // 初始化各种连接池
        $autoInitPool = $this['config']->get('auto_init_pool', []);
        if ($autoInitPool) {
            foreach ($autoInitPool as $pool) {
                $initFuncName = 'init' . ucfirst($pool) . 'Pool';
                $this->$initFuncName($workerId);
            }
        }
    }

    /**
     * 初始化 MySQL 连接池
     *
     * @param int $workerId
     */
    protected function initMysqlPool(int $workerId)
    {
        // 连接池管理器
        $poolManager = $this->get('pool.manager');

        // 加载 MySQL 配置
        if (! ($poolConfig = $this['config']->load('mysql', true))) {
            return;
        }

        foreach ($poolConfig as $name => $config) {
            // 注册 master 连接到连接池
            $poolManager->register($this->get('client.mysql.pool', $name, $config['master'])->init());

            // 处理 slave
            if (! isset($config['slave']) or ! $config['slave']) {
                continue;
            }

            // 只有单个 slave
            if (isset($config['slave']['host'])) {
                $config['slave'] = [$config['slave']];
            }

            $slave = [];
            foreach ($config['slave'] as $key => $item) {
                $key = $name . '_' . $key;

                $slave[] = $key;
                // 注册 slave 连接到连接池
                $poolManager->register($this->get('client.mysql.pool', $key, $item)->init());
            }

            // 把 slave 列表加入到动态配置表，方便自动判断使用哪一个 slave
            if ($workerId == 0) {
                $this->setConfig('_mysql.' . $name, $slave);
            }
        }
        unset($poolConfig);
    }

    /**
     * @param int $workerId
     */
    protected function initTcpPool(int $workerId)
    {
        unset($workerId);

        // 连接池管理器
        $poolManager = $this->get('pool.manager');

        // 加载 Tcp 配置
        $poolConfig = $this['config']->load('tcp', true);
        if (! $poolConfig) {
            return;
        }

        foreach ($poolConfig as $name => $config) {
            // 注册到连接池
            $poolManager->register($this->get('client.tcp.pool', $name, $config)->init());
        }
        unset($poolConfig);
    }

    /**
     * @param int $workerId
     */
    protected function initRedisPool(int $workerId)
    {
        // 连接池管理器
        $poolManager = $this->get('pool.manager');

        // 加载 Redis 配置
        $redisConfig = $this['config']->load('redis', true);
        if (! $redisConfig) {
            return;
        }

        // 是否启用了查询缓存
        $isEnableQueryCache = $this['config']->get('enable_query_cache', false);

        foreach ($redisConfig as $name => $config) {
            // 未开启查询缓存
            if (strpos($name, 'query_cache') !== false and ! $isEnableQueryCache) {
                continue;
            }

            // 一组 Redis
            if (! array_key_exists('host', $config)) {
                $group = [];
                foreach ($config as $key => $item) {
                    $key = $name . '_' . $key;
                    $group[] = $key;

                    // 注册到连接池
                    $poolManager->register(
                        $this->get('client.redis.pool', $key, $item)->init(),
                        $item['alias'] ?? []
                    );
                }

                // 把 Redis 组列表加入到动态配置表，方便自动判断使用哪一个
                if ($workerId == 0) {
                    $this->setConfig('_redis.' . $name, $group);
                }
            }
            // 单个 Redis
            else {
                // 注册到连接池
                $poolManager->register(
                    $this->get('client.redis.pool', $name, $config)->init(),
                    $config['alias'] ?? []
                );
            }
        }
        unset($redisConfig);
    }

    /**
     * 获取动态配置
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function getConfig(string $key = null, $default = null)
    {
        $packet = $this->get('packet');

        // 根据 key 获取配置
        if ($key) {
            $data = $this->configTable->get($key);

            if (! $data) {
                return $default;
            }

            return $packet->unpack($data['value']);
        }

        // 返回全部配置
        $data = [];
        foreach ($this->configTable as $k => $v) {
            $data[$k] = $packet->unpack($v['value']);
        }

        return $data;
    }

    /**
     * 动态配置是否存在
     *
     * @param string $key
     * @return bool
     */
    public function hasConfig(string $key)
    {
        return $this->configTable->exist($key);
    }

    /**
     * 设置动态配置
     *
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function setConfig(string $key, $value)
    {
        $this->configTable->set($key, [
            'value' => $this['packet']->pack($value),
        ]);

        return $this;
    }

    /**
     * 删除动态配置
     *
     * @param string $key
     * @return $this
     */
    public function delConfig(string $key)
    {
        if ($this->hasConfig($key)) {
            $this->configTable->del($key);
        }

        return $this;
    }

    /**
     * 系统运行错误
     *
     * @param $errNo
     * @param $errStr
     * @param $errFile
     * @param $errLine
     * @param $errContext
     */
    public function onError($errNo, $errStr, $errFile, $errLine, $errContext)
    {
        // debug 模式打印出来，方便调试
        if (DEBUG_MODEL) {
            echo $errStr . "\n";
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            echo "\n";
        }

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
     * 检查有没有错误
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

            Log::error('服务崩溃', $log);
        }
        unset($error);
    }

    /**
     * 记录运行信息
     *
     * @param bool  $status
     * @param float $runTime
     */
    public function logRunInfo(bool $status = true, float $runTime = 0.0)
    {
        $this->runTable->incr('total', $status ? 'success' : 'failure', 1);
        $this->runTable->incr('total', 'time', $runTime);

        $this->runTable->incr('qps_' . \time(), 'success', 1);
    }

    /**
     * 记录最大 QPS
     *
     * @param $worker
     */
    protected function logMaxQps($worker)
    {
        $worker->tick(
            1000,
            function () {
                $time = \time();
                $prevSec = $this->runTable->get('qps_' . ($time - 1));
                if (! $prevSec) {
                    return;
                }

                // 删除前10~15秒记录
                $except = ['qps_max' => true];
                for ($i = 0; $i < 5; $i++) {
                    $except['qps_' . ($time - $i)] = true;
                }

                foreach ($this->runTable as $k => $v) {
                    if (strpos($k, 'qps_') !== false and ! isset($except[$k])) {
                        $this->runTable->del($k);
                    }
                }
                unset($except);

                // qps max
                $needAdd = true;
                if ($qpsMax = $this->runTable->get('qps_max')) {
                    if ($qpsMax['success'] < $prevSec['success']) {
                        $this->runTable->del('qps_max');
                    } else {
                        $needAdd = false;
                    }
                }

                if (! $needAdd) {
                    return;
                }

                $this->runTable->set('qps_max', [
                    'time'    => 0,
                    'success' => $prevSec['success'],
                    'failure' => 0,
                ]);
            }
        );
    }
}