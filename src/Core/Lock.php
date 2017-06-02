<?php

namespace Flower\Core;

use Flower\Client\Redis;

/**
 * Class Lock
 *
 * @package Flower\Utility
 */
class Lock
{
    /**
     * @var \Redis
     */
    private $redis;

    /**
     * Lock constructor.
     * @param Redis $redis
     */
    public function __construct(Redis $redis)
    {
        $this->redis = $redis->use('lock');
    }

    /**
     * @param $key
     * @param int $expire
     * @return bool
     */
    public function lock($key, $expire = 5)
    {
        $time  = time();
        $value = $time + $expire;
        $isLock = yield $this->redis->set($key, $value, ['NX', 'EX' => $expire]);
        if (! $isLock) {
            $lockTime = yield $this->redis->get($key);

            if ($time > $lockTime) {
                $this->unlock($key);
                $isLock = yield $this->redis->set($key, $value, ['NX', 'EX' => $expire]);
            }
        }

        return $isLock ? false : true;
    }

    /**
     * @param $key
     * @return \Generator
     */
    public function unlock($key)
    {
        return yield $this->redis->del($key);
    }
}
