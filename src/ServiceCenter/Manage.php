<?php

namespace Weipaitang\Framework\ServiceCenter;

use Weipaitang\Config\ConfigInterface;
use Weipaitang\Framework\Controller;
use Weipaitang\Framework\RunInfo;

/**
 * Class Manage
 * @package Weipaitang\Framework\ServiceCenter
 */
class Manage extends Controller
{
    /**
     * @param array $data
     * @param int   $fd
     */
    public function run(array $data, int $fd = null)
    {
        $this->withFd($fd);
        $this->withFromId($fd);

        switch ($data['request']) {
            case 'heartbeat':
                $this->send('live');
                break;
            case 'status':
                $this->status();
                break;
            case 'config':
                $this->config($data['param'] ?: []);
                break;
        }
    }

    /**
     * @param $data
     */
    protected function config($data)
    {
        if (! empty($data)) {
            /**
             * @var ConfigInterface $config
             */
            $config = $this->container->get('config');

            foreach ($data as $k => $v) {
                $config->set($k, $v);
            }
        }

        $this->send('success');
    }

    /**
     * @return void
     */
    protected function status()
    {
        $status = $this->getServer()->stats();
        unset($status['worker_request_count']);

        $status['load_avg'] = array_map(function ($v) {
            return round($v, 2);
        }, sys_getloadavg());

        /**
         * @var RunInfo $runInfo
         */
        $runInfo = $this->container->get('runinfo');

        $total = $runInfo->get('total');
        $status['total'] = [
            'success'  => $total ? $total['success'] : 0,
            'failure'  => $total ? $total['failure'] : 0,
            'avg_time' => ($total['success'] or $total['failure'])
                ? bcdiv($total['time'], ($total['success'] + $total['failure']), 7)
                : 0,
        ];
        unset($total);

        $time = time();
        $startTime = $time - $status['start_time'];

        $total  = $status['total']['success'] + $status['total']['failure'];

        $qpsSec = $runInfo->get('qps_' . ($time - 1))['success'] ?? 0;
        $qpsAvg = $startTime ? ceil($total / $startTime) : $total;
        $qpsMax = $runInfo->get('qps_max')['success'] ?? 0;

        $status['qps'] = "{$qpsSec}, {$qpsAvg}, {$qpsMax}";

        $this->send($status);
    }
}
