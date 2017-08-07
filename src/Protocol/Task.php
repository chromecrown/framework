<?php

namespace Weipaitang\Framework\Protocol;

use Weipaitang\Console\Output;
use Weipaitang\Log\Log;
use Weipaitang\Packet\MsgpackHandler;
use Weipaitang\Server\Server;
use Swoole\Server as SwooleServer;

/**
 * Class Task
 *
 * @package Weipaitang\Framework\Dispatcher
 */
class Task extends Protocol
{
    /**
     * @var array
     */
    protected $register = [
        Server::ON_TASK   => 'onTask',
        Server::ON_FINISH => 'onFinish',
    ];

    /**
     * @param SwooleServer $server
     * @param int          $taskId
     * @param int          $workerId
     * @param array        $data
     */
    public function onTask(SwooleServer $server, int $taskId, int $workerId, array $data)
    {
        if (! isset($data['request'])
            or ! $data['request']
            or ! isset($data['method'])
            or ! $data['method']
        ) {
            Log::error('Task not found.');
            return;
        }

        $data['args'] = $data['args'] ?? [];
        
        $this->app->get('coroutine')->withTask(
            $this->dispatch($server, $data)
        )->start();
    }

    /**
     * @param SwooleServer $server
     * @param array        $data
     */
    private function dispatch(SwooleServer $server, array $data)
    {
        $isManageTask = $data['request'] == 'manage.task';
        if ($isManageTask) {
            $request   = 'Task';
            $namespace = '\Weipaitang\Framework\ServiceCenter\\';
        } else {
            $request   = join('\\', array_map('ucfirst', explode('/', $data['request'])));
            $namespace = '\App\Task\\';
        }

        if (! class_exists($namespace . $request)) {
            Log::error("Task not found. [{$request}]");
            return ;
        }

        $request = $namespace . $request;
        $method  = $data['method'];

        $object = $this->app->make($request);

        if (! method_exists($object, $method)) {
            Log::error("Task not found. [{$request}:{$method}]");
            return;
        }

        if (! $isManageTask) {
            $this->logRequest('task', $request, $method, $data['args']);
        }
        
        $lockKey = '';
        $lockHandler = null;
        if (isset($data['lock']) and $data['lock']) {
            $lockKey = md5(
                $request
                . $method
                . (new MsgpackHandler)->pack($data['args'])
            );

            $lockHandler = $this->app->get('lock');

            if (yield $lockHandler->lock($lockKey)) {
                Output::debug("The task has been locked. [{$request}:{$method}]", 'blue');

                return;
            }
        }

        $result = null;
        try {
            $result = yield $object->$method(...array_values($data['args']));
            unset($data);
        } catch (\Exception $e) {
            $lockKey && yield $lockHandler->unlock($lockKey);

            Log::error($e->getMessage());
        }

        $lockKey && yield $lockHandler->unlock($lockKey);
        unset($lockHandler);

        $server->finish((new MsgpackHandler)->pack($result));
    }

    public function onFinish(SwooleServer $server, int $taskId, string $data)
    {

    }
}
