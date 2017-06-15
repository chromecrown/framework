<?php

namespace Flower\Client\Pool;

use Flower\Log\Log;
use Flower\Pool\Pool;
use Flower\Utility\Console;
use Flower\Coroutine\CoroutineInterface;
use Swoole\MySQL as SwooleMySQL;

/**
 * Class MySQL
 *
 * @package Flower\Client\Pool
 */
class MySQL extends Pool implements CoroutineInterface
{
    protected $type = 'mysql';
    protected $failure = [
        'affected_rows' => null,
        'insert_id'     => null,
        'result'        => nil,
    ];

    private $bind = [];
    private $sql;
    private $bindId;

    /**
     * @param callable $callback
     * @param string   $sql
     * @param int      $bindId
     */
    public function call(callable $callback, string $sql = null, int $bindId = null)
    {
        $this->sql    = $sql;
        $this->bindId = $bindId;

        $this->send($callback);
    }

    /**
     * 发送查询请求
     *
     * @param  null|callable $callback
     * @throws \Exception
     */
    public function send(callable $callback)
    {
        $sqlError = false;
        if (! $this->sql) {
            $sqlError = true;

            Log::error('SQL 不能为空');
        } else {
            $sqlFormat = strtoupper(trim($this->sql));
            if (substr($sqlFormat, 0, 6) !== 'INSERT') {
                if (strpos($sqlFormat, 'WHERE') === false and strpos($sqlFormat, 'LIMIT') === false) {
                    $sqlError = true;

                    Log::error('SQL 不能没有 WHERE | LIMIT 条件, SQL: ' . $this->sql);
                }

                unset($sqlFormat);
            }
        }

        if ($sqlError) {
            $this->reset();
            $callback($this->failure);

            return;
        }

        $data = [
            'sql'   => $this->sql,
            'token' => $this->getToken($callback),
            'retry' => 0,
        ];

        if ($this->bindId) {
            $data['bind_id'] = $this->bindId;
        }

        $this->reset();
        $this->execute($data);
    }

    /**
     * 生成查询请求
     *
     * @param  string       $sql
     * @param  null|integer $bindId
     * @return \Generator
     */
    public function query(string $sql, $bindId = null)
    {
        $this->sql = $sql;
        $this->bindId = $bindId;

        return yield $this;
    }

    /**
     * 执行查询
     *
     * @param  $data
     * @throws \Exception
     */
    public function execute(array $data)
    {
        $client = $this->getClient($data);
        if (! $client) {
            return;
        }

        $sTime = microtime(true);
        $client->query(
            $data['sql'],
            function ($client, $result) use ($data, $sTime) {
                $sql = $data['sql'];
                $bindId = $data['bind_id'] ?? null;

                if ($result === false) {
                    Log::error('MySQL 查询错误', [$client->error, $data]);
                    $data['result']['error'] = "[sql]: {$sql} [error]: {$client->error}";
                }
                unset($data['sql'], $data['bind_id']);

                if ($bindId) {
                    // 结束事务
                    if ($sql == 'commit' or $sql == 'rollback') {
                        $this->releaseBind($bindId);
                    }
                } else {
                    $this->release($client);
                }

                $data['result']['result'] = $result;
                $data['result']['affected_rows'] = $client->affected_rows;
                $data['result']['insert_id'] = $client->insert_id;

                if (isset($data['result']['error'])) {
                    $data['result']['result'] = nil;
                }

                $this->callback($data['token'], $data['result']);

                if (app()->getConfig('enable_slow_log', false)) {
                    $this->logSlow($sql, $sTime);
                }
                unset($data);
            }
        );
    }

    /**
     * @param array $data
     * @return bool|SwooleMySQL
     * @throws \Exception
     */
    private function getClient(array $data)
    {
        $client = null;
        $bindId = $data['bind_id'] ?? null;

        // 事物绑定
        if ($bindId) {
            $client = $this->bind[$bindId] ?? null;

            if ($client == null and $data['sql'] != 'begin') {
                throw new \Exception('MySQL 事物异常');
            }
        }

        if ($client == null) {
            $client = $this->getConnection();
            if (! $client) {
                $this->retry($data);

                return false;
            }

            // 添加事务绑定
            if ($bindId != null) {
                $this->bind[$bindId] = $client;
            }
        }

        return $client;
    }

    /**
     * Connect MySQL
     */
    public function connect()
    {
        $this->waitConnect++;

        $client = new SwooleMySQL();
        $client->on('Close', [$this, 'close']);
        $client->connect($this->config, function ($client, $result) {
            $this->waitConnect--;

            if (! $result) {
                $this->close($client);
            } else {
                if (! isset($client->isConnected)) {
                    $this->currConnect++;
                }

                $client->isConnected = true;
                $this->release($client);
            }
        });
    }

    /**
     * MySQL连接关闭
     *
     * @param $client
     */
    public function close(SwooleMySQL $client)
    {
        if (isset($client->connect_error)) {
            Log::error('MySQL Error: ' . $client->connect_error);
        }

        $client->isConnected = false;

        $this->release($client);
    }

    /**
     * @param int $bindId
     */
    public function releaseBind(int $bindId)
    {
        $client = $this->bind[$bindId];
        unset($this->bind[$bindId]);

        if ($client != null) {
            $this->release($client);
        }
    }

    /**
     * reset
     */
    private function reset()
    {
        $this->sql = null;
        $this->bindId = null;
    }

    /**
     * @param string $sql
     * @param float  $sTime
     */
    private function logSlow(string $sql, $sTime)
    {
        $slowTime = app()->getConfig('slow_time', 0.1);
        $useTime = microtime(true) - $sTime;

        if ($useTime < $slowTime) {
            return;
        }

        $message = 'MySQL Async [' . number_format($useTime, 5) . '] : ' . $sql;

        Log::info($message);
        Console::debug($message, 'blue');
    }
}
