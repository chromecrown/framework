<?php

namespace Wpt\Framework\Client\Pool;

use Ramsey\Uuid\Uuid;
use Wpt\Framework\Log\Log;
use Wpt\Framework\Pool\Pool;
use Wpt\Framework\Utility\Console;
use Wpt\Framework\Coroutine\CoroutineInterface;
use Swoole\Redis as SwooleRedis;

/**
 * Class Redis
 *
 * @package Wpt\Framework\Client\Pool
 */
class Redis extends Pool implements CoroutineInterface
{
    /**
     * @var string
     */
    protected $type = 'redis';

    /**
     * @var array
     */
    private $bind = [];

    /**
     * @var string
     */
    private $bindId;

    /**
     * @var string
     */
    private $method;

    /**
     * @var mixed
     */
    private $arguments;

    /**
     * @var boolean
     */
    private $enableLogSlow = null;

    /**
     * @param string $bindId
     * @return $this
     */
    public function bind(string $bindId = null)
    {
        if ($bindId) {
            $this->bindId = $bindId;
        }

        return $this;
    }

    /**
     * @param callable $callback
     * @param string   $method
     * @param array    $arguments
     * @param bool     $enableLogSlow
     */
    public function call(callable $callback, string $method = null, array $arguments = null, bool $enableLogSlow = null)
    {
        $this->method        = $method;
        $this->arguments     = $arguments;
        $this->enableLogSlow = $enableLogSlow;

        $this->send($callback);
    }

    /**
     * @param callable $callback
     */
    public function send(callable $callback)
    {
        if ($this->enableLogSlow === null) {
            $this->enableLogSlow = app()->getConfig('enable_slow_log', false);
        }

        $data = [
            'name'      => $this->method,
            'arguments' => $this->arguments,
            'token'     => $this->getToken($callback),
            'retry'     => 0,
            'bind_id'   => $this->bindId,
            'log_slow'  => $this->enableLogSlow,
        ];

        $this->method = $this->arguments = $this->bindId = $this->enableLogSlow = null;

        $this->execute($data);
    }

    /**
     * @param string $name
     * @param array  $arguments
     * @return \Generator
     */
    public function query(string $name, array $arguments)
    {
        $this->method = $name;
        $this->arguments = $arguments;

        return yield $this;
    }

    /**
     * @param array $data
     */
    public function execute(array $data)
    {
        $client = $this->getClient($data);
        if (! $client) {
            return;
        }

        list($arguments, $data) = $this->parseArguments($data);

        $sTime = microtime(true);
        $arguments[] = function ($client, $result) use (& $data, $sTime) {
            $cmd = strtolower($data['name']);
            if ($cmd === 'multi') {
                $uuid = Uuid::uuid4()->toString();
                $this->bind[$uuid] = $client;

                $this->callback($data['token'], app('redis', $data['trace'], $this->getName())->bind($uuid));
                return;
            }

            if ($cmd === 'exec') {
                $this->releaseBind($data['bind_id']);
            } else {
                if ($data['bind_id']) {
                    $this->callback($data['token'], null);
                    return;
                }

                $this->release($client);
            }

            $data = $this->parseResult($data, $result);

            $this->callback($data['token'], $data['result']);

            if ($data['log_slow']) {
                $this->logSlow($data, $sTime);
            }
            unset($data);
        };

        $client->__call($data['name'], array_values($arguments));
    }

    /**
     * @param $data
     * @return bool|mixed|null
     * @throws \Exception
     */
    private function getClient($data)
    {
        $client = null;
        $bindId = $data['bind_id'] ?? null;

        // 事物绑定
        if ($bindId) {
            $client = $this->bind[$bindId] ?? null;

            if ($client == null) {
                throw new \Exception('Redis 事物异常');
            }
        }

        if ($client == null) {
            $client = $this->getConnection();
            if (! $client) {
                $this->retry($data);
                return false;
            }
        }

        return $client;
    }

    /**
     * @param $bindId
     */
    public function releaseBind($bindId)
    {
        $client = $this->bind[$bindId];
        unset($this->bind[$bindId]);

        if ($client != null) {
            $this->release($client);
        }
    }

    /**
     * connect redis
     */
    public function connect()
    {
        $this->waitConnect++;

        $client = new SwooleRedis();
        $client->on('Close', [$this, 'close']);
        $client->connect(
            $this->config['host'],
            $this->config['port'],
            function ($client, $result) {
                $this->waitConnect--;

                if (! $result) {
                    $this->close($client);

                    return;
                }

                (isset($this->config['auth']) and $this->config['auth'])
                    ? $this->auth($client)
                    : $this->select($client);
            }
        );
    }

    /**
     * @param SwooleRedis $client
     */
    private function auth(SwooleRedis $client)
    {
        $client->auth($this->config['auth'], function ($client, $result) {
            if (! $result) {
                $this->close($client);

                return;
            }

            $this->select($client);
        });
    }

    /**
     * 选择数据库并释放到连接池
     *
     * @param $client
     */
    private function select(SwooleRedis $client)
    {
        // 存在select
        if (isset($this->config['select'])) {
            $client->select($this->config['select'], function ($client, $result) {
                if (! $result) {
                    $this->close($client);

                    return;
                }

                $this->currConnect++;

                $client->isConnected = true;
                $this->release($client);
            });
        } else {
            $this->currConnect++;

            $client->isConnected = true;
            $this->release($client);
        }
    }

    /**
     * @param SwooleRedis $client
     */
    public function close(SwooleRedis $client)
    {
        if (isset($client->errMsg)) {
            Log::error('Redis Error: ' . $client->errMsg);
        }

        $client->isConnected = false;

        $this->release($client);
    }

    /**
     * 分析参数
     *
     * @param  $data
     * @return array
     */
    public function parseArguments($data)
    {
        $arguments = $data['arguments'];

        // 特别处理下M命令(批量)
        switch (strtolower($data['name'])) {
            case 'srem':
            case 'sadd':
                $key = $arguments[0];
                if (is_array($arguments[1])) {
                    $arguments = $arguments[1];
                }
                array_unshift($arguments, $key);
                break;

            case 'del':
            case 'delete':
                if (is_array($arguments[0])) {
                    $arguments = $arguments[0];
                }
                break;

            case 'mset':
                $harray = $arguments[0];
                unset($arguments[0]);

                foreach ($harray as $key => $value) {
                    $arguments[] = $key;
                    $arguments[] = $value;
                }

                $data['arguments'] = $arguments;
                $data['M'] = $harray;
                break;

            case 'hmset':
                $harray = $arguments[1];
                unset($arguments[1]);

                foreach ($harray as $key => $value) {
                    $arguments[] = $key;
                    $arguments[] = $value;
                }

                $data['arguments'] = $arguments;
                $data['M'] = $harray;
                break;

            case 'mget':
                $harray = $arguments[0];
                unset($arguments[0]);

                $arguments = array_merge($arguments, $harray);
                $data['arguments'] = $arguments;
                $data['M'] = $harray;
                break;

            case 'hmget':
                $harray = $arguments[1];
                unset($arguments[1]);

                $arguments = array_merge($arguments, $harray);
                $data['arguments'] = $arguments;
                $data['M'] = $harray;
                break;
            default:
                break;
        }

        return [$arguments, $data];
    }

    /**
     * 分析返回结果
     *
     * @param  $data
     * @param  $result
     * @return mixed
     */
    public function parseResult($data, $result)
    {
        switch (strtolower($data['name'])) {
            case 'hmget':
            case 'mget':
                $data['result'] = [];
                $count = count($result);

                for ($i = 0; $i < $count; $i++) {
                    $data['result'][$data['M'][$i]] = $result[$i];
                }
                break;

            case 'hgetall':
                $data['result'] = [];
                $count = count($result);

                for ($i = 0; $i < $count; $i = $i + 2) {
                    $data['result'][$result[$i]] = $result[$i + 1];
                }
                break;

            case 'type':
                switch ($result) {
                    case 'string':
                        $result = \Redis::REDIS_STRING;
                        break;
                    case 'list':
                        $result = \Redis::REDIS_LIST;
                        break;
                    case 'set':
                        $result = \Redis::REDIS_SET;
                        break;
                    case 'zset':
                        $result = \Redis::REDIS_ZSET;
                        break;
                    case 'hash':
                        $result = \Redis::REDIS_HASH;
                        break;
                    case 'none':
                        $result = null;
                        break;
                }

                $data['result'] = $result;
                break;

            default:
                $data['result'] = $result;
        }
        unset($data['M'], $result);

        return $data;
    }

    /**
     * @param $data
     * @param $sTime
     */
    private function logSlow(& $data, $sTime)
    {
        $slowTime = app()->getConfig('slow_time', 0.1);
        $useTime  = microtime(true) - $sTime;

        if ($useTime < $slowTime) {
            return;
        }

        $arguments = substr(json_encode(array_values($data['arguments'])), 1, -1);
        $message = 'Redis Async ['
            . number_format($useTime, 5)
            . '] : '
            . $data['name']
            . '('
            . ($arguments ?: '...')
            . ')';

        Log::info($message);

        if (DEBUG_MODEL) {
            Console::debug($message, 'blue');
        }
    }
}
