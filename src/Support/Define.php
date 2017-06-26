<?php

namespace Flower\Support;

/**
 * Class Define
 *
 * @package Flower\Support
 */
class Define
{
    const VERSION = 'v1.0';

    const ON_TASK_FINISH    = 'on_task_finish';
    const ON_PIPE_MESSAGE   = 'on_pipe_message';

    const HOOK_TASK_INIT    = 'task_init';
    const HOOK_WORKER_INIT  = 'worker_init';
    const HOOK_SERVER_INIT  = 'server_init';
    const HOOK_SERVER_START = 'server_start';
    const HOOK_SERVER_STOP  = 'server_stop';

    const BINDINGS = [
        'redis'               => [\Flower\Client\Redis::class, false],
        'multi'               => [\Flower\Client\Multi::class, false],
        'client.redis.pool'   => [\Flower\Client\Pool\Redis::class, false],
        'client.redis.sync'   => [\Flower\Client\Sync\Redis::class, false],
        'client.mysql.pool'   => [\Flower\Client\Pool\MySQL::class, false],
        'client.mysql.sync'   => [\Flower\Client\Sync\MySQL::class, false],
        'client.tcp'          => [\Flower\Client\Tcp::class, false],
        'client.tcp.pool'     => [\Flower\Client\pool\Tcp::class, false],
        'client.tcp.sync'     => [\Flower\Client\Sync\Tcp::class, false],
        'client.tcp.async'    => [\Flower\Client\Async\Tcp::class, false],
        'client.http.async'   => [\Flower\Client\Async\Http::class, false],
        'client.file.async'   => [\Flower\Client\Async\File::class, false],
        'client.dns.async'    => [\Flower\Client\Async\Dns::class, false],
        'config'              => [\Flower\Core\Config::class, true],
        'lock'                => [\Flower\Core\Lock::class, false],
        'packet'              => [\Flower\Core\Packet::class, true],
        'co.scheduler'        => [\Flower\Coroutine\Scheduler::class, false],
        'co.task'             => [\Flower\Coroutine\Task::class, false],
        'model'               => [\Flower\Database\Model::class, false],
        'expression'          => [\Flower\Database\Expression::class, false],
        'query.builder'       => [\Flower\Database\QueryBuilder::class, false],
        'query.builder.cache' => [\Flower\Database\QueryBuilderCache::class, false],
        'pool.manager'        => [\Flower\Pool\Manager::class, true],
        'command'             => [\Flower\Server\Command::class, false],
        'server'              => [\Flower\Server\Server::class, true],
        'file'                => [\Flower\Utility\File::class, false],
        'time'                => [\Flower\Utility\Time::class, false],
        'console'             => [\Flower\Utility\Console::class, false],
        'manage.register'     => [\Flower\Manage\Register::class, false],
        'manage.service'      => [\Flower\Manage\Service::class, false],
        'dispatcher.tcp'      => [\Flower\Dispatcher\Tcp::class, false],
        'dispatcher.http'     => [\Flower\Dispatcher\Http::class, false],
        'dispatcher.task'     => [\Flower\Dispatcher\Task::class, false],
        'log'                 => [\Flower\Log\Logger::class, true],
        'log.file'            => [\Flower\Log\FileHandler::class, true],
        'middleware'          => [\Flower\Middleware\Middleware::class, false],
        'route'               => [\Flower\Http\Route::class, true],
        'request'             => [\Flower\Http\Request::class, false],
        'response'            => [\Flower\Http\Response::class, false],
    ];
}