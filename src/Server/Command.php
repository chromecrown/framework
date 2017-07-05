<?php

namespace Wpt\Framework\Server;

use Wpt\Framework\Utility\Time;
use Wpt\Framework\Support\Define;
use Wpt\Framework\Utility\Console;
use Wpt\Framework\Support\Construct;

/**
 * Class Command
 *
 * @package Wpt\Framework\Server
 */
class Command
{
    use Construct;

    private $serverName;
    private $pidFile;
    private $daemon = false;

    /**
     * @return bool
     */
    public function run()
    {
        $this->pidFile    = $this->server->getPidFile();
        $this->serverName = $this->server->getServerName();

        // 分析命令
        $command = $this->parseCommand();

        // 执行命令
        switch ($command) {
            case 'start':
                $this->start();
                break;
            case 'reload':
                $this->reload();
                break;
            case 'stop':
                $this->stop('stop');
                break;
            case 'restart':
                $this->stop('restart');
                break;
            case 'status':
                $this->status();
                break;
            case 'kill':
                $this->kill();
                break;
        }

        return $this->daemon;
    }

    /**
     * 分析命令
     */
    private function parseCommand()
    {
        $argv = array_map('trim', $_SERVER['argv']);

        // 检查命令是否支持
        if (! isset($argv[1]) or ! in_array($argv[1], ['start', 'stop', 'reload', 'restart', 'status', 'kill'])) {
            Console::write("Usage: php {$argv[0]} {start|stop|reload|restart|kill|status}");
            exit(1);
        }

        $command = trim($argv[1]);
        $command2 = $argv[2] ?? '';

        if ($command == 'restart' or ($command == 'start' and $command2 === '-d')) {
            $this->daemon = true;
        }

        return $command;
    }

    /**
     * 检查命令
     *
     * @param  null $command
     * @return array
     */
    private function checkCommand($command = null)
    {
        $masterPid = $managePid = null;
        if (file_exists($this->pidFile)) {
            list($masterPid, $managePid) = explode(',', file_get_contents($this->pidFile));
        }

        $flag = false;
        if ($masterPid) {
            $flag = @posix_kill($masterPid, 0);
        }

        if ($command) {
            if ($command == 'start' and $flag) {
                Console::write("{$this->serverName} already running");
                exit(0);
            } elseif ($command != 'start' and ! $flag) {
                Console::write("{$this->serverName} not run");
                exit(0);
            }
        }

        return [$flag, $masterPid, $managePid];
    }

    /**
     * 启动服务
     */
    private function start()
    {
        $this->checkCommand('start');

        $port = $this->getServerPort();
        if (! $port) {
            return;
        }

        if (PHP_OS == 'Linux') {
            foreach ($port as $v) {
                $result = exec("netstat -apn | grep :{$v} | awk '{print \$4}' | grep :{$v} | wc -l");
                $result = (int)trim($result);

                if ($result > 0) {
                    Console::write("端口号被占用，请检查。Port: {$v}", 'red');
                    exit(1);
                }
            }
        }

        // server start
        $this->server->hook(Define::HOOK_SERVER_START);
    }

    /**
     * 重载服务
     */
    private function reload()
    {
        list(, , $managePid) = $this->checkCommand('reload');

        @posix_kill($managePid, SIGUSR1);
        Console::write("{$this->serverName} reloaded");
        exit(0);
    }

    /**
     * 停止服务
     *
     * @param $command
     */
    private function stop($command)
    {
        list(, $masterPid,) = $this->checkCommand('stop');

        Console::write("{$this->serverName} is stoping ...");

        $masterPid && @posix_kill($masterPid, SIGTERM);

        $timeout = 8;
        $startTime = time();

        while (true) {
            if ($masterPid && @posix_kill($masterPid, 0)) {
                if ((time() - $startTime) >= $timeout) {
                    Console::write("{$this->serverName} stop fail");
                    exit(1);
                }

                usleep(10000);
                continue;
            }

            @unlink($this->pidFile);
            Console::write("{$this->serverName} stop success");

            if ($command == 'stop') {
                // server stop
                $this->server->hook(Define::HOOK_SERVER_STOP);

                exit(0);
            }

            break;
        }
    }

    /**
     * 杀进程
     */
    private function kill()
    {
        $title = "为保证安全，请手动执行\n";
        $kill = "ps aux | grep '%s' | grep -v grep | awk '{print \$2}' | xargs kill -9\n";

        if (PHP_OS == 'Darwin') {
            $title .= sprintf($kill, $_SERVER['argv'][0]);
        } else {
            $port = $_SERVER['argv'][2] ?? $this->getServerPort();
            ! is_array($port) and ($port = [$port]);

            foreach ($port as $v) {
                $title .= sprintf($kill, $this->serverName . '\[.*:' . $v);
            }
        }

        Console::write($title);
        exit(0);
    }

    /**
     * @return array
     */
    private function getServerPort()
    {
        $port = [];
        if ($this->app['config']->get('enable_tcp_server', false)) {
            $port[] = $this->app['config']->get('tcp_server_port');
        }

        if ($this->app['config']->get('enable_http_server')) {
            $port[] = $this->app['config']->get('http_server_port');
        }

        return $port;
    }

    /**
     * 服务状态
     */
    private function status()
    {
        $prefixLen = ceil((80 - strlen($this->serverName) - 2) / 2);
        $suffixLen = 80 - $prefixLen - strlen($this->serverName) - 2;

        $firstSplitLine = str_pad('', $prefixLen, '-');
        $firstSplitLine .= "\033[47;30m {$this->serverName} \033[0m";
        $firstSplitLine .= str_pad('', $suffixLen, '-') . "\n";

        $statusSplitLine = '------------------------------------';
        $statusSplitLine .= "\033[47;30m Total \033[0m";
        $statusSplitLine .= "-------------------------------------\n";

        $retry = 0;
        $config = $this->server->getConfig();

        do {
            $status = $this->getServerStatus($config);

            $loadAvg = array_map(function ($v) {
                return round($v, 2);
            }, sys_getloadavg());

            $display = $firstSplitLine;

            $display .= "PHP version: "
                . Define::VERSION
                . str_pad('', 28 - strlen(PHP_VERSION))
                . "Swoole version: "
                . SWOOLE_VERSION
                . "\n";

            $display .= "Framework version: "
                . Define::VERSION
                . str_pad('', 21 - strlen(Define::VERSION))
                . "Worker number: {$config['worker_num']}, {$config['task_worker_num']}"
                . "\n";

            if ($status) {
                $runTime = Time::format(time() - $status['start_time'] ?? 0, false);
                $display .= "Run: {$runTime}\n";
            }

            $display .= "Load average: " . implode(", ", $loadAvg) . "\n";

            if ($status) {
                $retry = 0;

                $display .= $statusSplitLine;
                $display .= "Connection num: {$status['connection_num']}" . str_pad('',
                        24 - strlen($status['connection_num']));
                $display .= "Tasking num: {$status['tasking_num']}\n";
                $display .= "Avg request time: {$status['total']['avg_time']}\n";
                $display .= "Total request: {$status['total']['success']}, {$status['total']['failure']}\n";
                $display .= "Qps: {$status['qps']}\n";
            } else {
                $display .= "get server status failure....\nretrying...{$retry}\n";
                $retry++;
            }

            $display .= str_pad('', 80, '-') . "\n";
            $display .= "Press Ctrl-C to quit. \n";

            Console::writeReplace($display);

            sleep(1);
        } while (true);
    }

    /**
     * @param        $set
     * @param string $type
     * @return array|int|null
     */
    public function getServerStatus($set, $type = 'all')
    {
        $status = $this->app->get('client.tcp.sync', [
                'set'    => $set,
                'config' => [
                    'host' => $this->app['config']->get('tcp_server_ip', '127.0.0.1'),
                    'port' => $this->app['config']->get('tcp_server_port', '9501'),
                ],
            ])->request(['type' => 'status'])['data'] ?? [];

        if ($type == 'avg_time') {
            return $status['total']['avg_time'] ?? 0;
        }

        return ($status and count($status) > 2) ? $status : null;
    }
}
