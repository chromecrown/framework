<?php

namespace Wpt\Framework\Support;

/**
 * Class Define
 *
 * @package Wpt\Framework\Support
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
        'redis'               => [\Wpt\Framework\Client\Redis::class, false],
        'multi'               => [\Wpt\Framework\Client\Multi::class, false],
        'client.redis.pool'   => [\Wpt\Framework\Client\Pool\Redis::class, false],
        'client.redis.sync'   => [\Wpt\Framework\Client\Sync\Redis::class, false],
        'client.mysql.pool'   => [\Wpt\Framework\Client\Pool\MySQL::class, false],
        'client.mysql.sync'   => [\Wpt\Framework\Client\Sync\MySQL::class, false],
        'client.tcp'          => [\Wpt\Framework\Client\Tcp::class, false],
        'client.tcp.pool'     => [\Wpt\Framework\Client\Pool\Tcp::class, false],
        'client.tcp.sync'     => [\Wpt\Framework\Client\Sync\Tcp::class, false],
        'client.tcp.async'    => [\Wpt\Framework\Client\Async\Tcp::class, false],
        'client.http.async'   => [\Wpt\Framework\Client\Async\Http::class, false],
        'client.file.async'   => [\Wpt\Framework\Client\Async\File::class, false],
        'client.dns.async'    => [\Wpt\Framework\Client\Async\Dns::class, false],
        'config'              => [\Wpt\Framework\Core\Config::class, true],
        'lock'                => [\Wpt\Framework\Core\Lock::class, false],
        'packet'              => [\Wpt\Framework\Core\Packet::class, true],
        'co.scheduler'        => [\Wpt\Framework\Coroutine\Scheduler::class, false],
        'co.task'             => [\Wpt\Framework\Coroutine\Task::class, false],
        'model'               => [\Wpt\Framework\Database\Model::class, false],
        'expression'          => [\Wpt\Framework\Database\Expression::class, false],
        'query.builder'       => [\Wpt\Framework\Database\QueryBuilder::class, false],
        'query.builder.cache' => [\Wpt\Framework\Database\QueryBuilderCache::class, false],
        'pool.manager'        => [\Wpt\Framework\Pool\Manager::class, true],
        'command'             => [\Wpt\Framework\Server\Command::class, false],
        'server'              => [\Wpt\Framework\Server\Server::class, true],
        'file'                => [\Wpt\Framework\Utility\File::class, false],
        'time'                => [\Wpt\Framework\Utility\Time::class, false],
        'console'             => [\Wpt\Framework\Utility\Console::class, false],
        'manage.register'     => [\Wpt\Framework\Manage\Register::class, false],
        'manage.service'      => [\Wpt\Framework\Manage\Service::class, false],
        'dispatcher.tcp'      => [\Wpt\Framework\Dispatcher\Tcp::class, false],
        'dispatcher.http'     => [\Wpt\Framework\Dispatcher\Http::class, false],
        'dispatcher.task'     => [\Wpt\Framework\Dispatcher\Task::class, false],
        'log'                 => [\Wpt\Framework\Log\Logger::class, true],
        'log.file'            => [\Wpt\Framework\Log\FileHandler::class, true],
        'middleware'          => [\Wpt\Framework\Middleware\Middleware::class, false],
        'route'               => [\Wpt\Framework\Http\Route::class, true],
        'request'             => [\Wpt\Framework\Http\Request::class, false],
        'response'            => [\Wpt\Framework\Http\Response::class, false],
    ];
}