<?php

namespace Wpt\Framework\Manage;

use Wpt\Framework\Support\Construct;

/**
 * Class Service
 *
 * @package Wpt\Framework
 */
class Service
{
    use Construct;

    /**
     * @param array $data
     * @param int   $fd
     */
    public function run(array $data, int $fd = null)
    {
        switch ($data['request']) {
            case 'heartbeat':
                $this->heartbeat($fd);
                break;
            case 'status':
                $this->status($fd);
                break;
            case 'config':
                $this->config($data['param'] ?: [], $fd);
                break;
        }
    }

    /**
     * 动态配置
     *
     * @param $data
     * @param $fd
     */
    protected function config($data, $fd)
    {
        if (! empty($data)) {
            foreach ($data as $k => $v) {
                $this->app->setConfig($k, $v);
            }
        }

        $this->server->send($fd, []);
    }

    /**
     * @param $fd
     */
    protected function heartbeat($fd)
    {
        $this->server->send($fd, 'live');
    }

    /**
     * @param $fd
     */
    protected function status($fd)
    {
        // 系统负载
        $loadAvg = array_map(function ($v) {
            return round($v, 2);
        }, sys_getloadavg());

        // 服务平均响应时间
        $requestAvgTime = $this->app['command']->getServerStatus($this->server->getConfig(), 'avg_time');

        $this->server->send($fd, [
            'load_avg'         => $loadAvg,
            'request_avg_time' => $requestAvgTime,
        ]);
    }
}
