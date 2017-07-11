<?php

namespace Weipaitang\Framework;

use Weipaitang\Config\Config;
use Weipaitang\Container\ContainerInterface;

class Pool
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var int
     */
    private $workerId;

    /**
     * @param ContainerInterface $container
     */
    public function withContainer(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config    = $container->get('config');
    }

    /**
     * @param $workerId
     */
    public function withWorkerId($workerId)
    {
        $this->workerId = $workerId;
    }

    /**
     * @return void
     */
    public function initPool()
    {
        $autoInitPool = $this->config->get('auto_init_pool', []);

        if ($autoInitPool) {
            foreach ($autoInitPool as $pool) {
                $initFuncName = 'init' . ucfirst($pool) . 'Pool';

                $this->$initFuncName();
            }
        }
    }

    protected function initMysqlPool()
    {
        // 连接池管理器
        $poolManager = $this->get('pool.manager');

        // 加载 MySQL 配置
        if (! ($poolConfig = $this->config->load('mysql'))) {
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
                $this->config->set('_mysql.' . $name, $slave);
            }
        }
        unset($poolConfig);
    }

    /**
     * @param int $workerId
     */
    protected function initTcpPool()
    {
        unset($workerId);

        // 连接池管理器
        $poolManager = $this->get('pool.manager');

        // 加载 Tcp 配置
        $poolConfig = $this->config->load('tcp');
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
    protected function initRedisPool()
    {
        // 连接池管理器
        $poolManager = $this->get('pool.manager');

        // 加载 Redis 配置
        $redisConfig = $this->config->load('redis');
        if (! $redisConfig) {
            return;
        }

        // 是否启用了查询缓存
        $isEnableQueryCache = $this->config->get('enable_query_cache', false);

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
                    $this->config->set('_redis.' . $name, $group);
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

}
