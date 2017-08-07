<?php

namespace Weipaitang\Framework\ServiceCenter;

use Weipaitang\Framework\RunInfo;
use Weipaitang\Framework\Task as AbstractTask;

/**
 * Class Task
 * @package Weipaitang\Framework\ServiceCenter
 */
class Task extends AbstractTask
{
    /**
     * @return array
     */
    public function status()
    {
        $status = $this->getServer()->stats();
        unset($status['worker_request_count']);

        $status['load_avg'] = array_map(function ($v) {
            return round($v, 2);
        }, sys_getloadavg());

        // linux system info
        if (PHP_OS == 'Linux') {
            $status = array_merge($status, (new SystemInfo)->getInfo());
        }

        /**
         * @var RunInfo $runInfo
         */
        $runInfo = $this->container->get('runinfo');

        $total = $runInfo->get('total');

        $status['process_success']  = $total ? $total['success'] : 0;
        $status['process_failure']  = $total ? $total['failure'] : 0;
        $status['process_avg_time'] = ($total['success'] or $total['failure'])
            ? bcdiv($total['time'], ($total['success'] + $total['failure']), 7)
            : 0;
        unset($total);

        $time = time();
        $startTime = $time - $status['start_time'];

        $total  = $status['total']['success'] + $status['total']['failure'];

        $qpsSec = $runInfo->get('qps_' . ($time - 1))['success'] ?? 0;
        $qpsAvg = $startTime ? ceil($total / $startTime) : $total;
        $qpsMax = $runInfo->get('qps_max')['success'] ?? 0;

        $status['qps'] = "{$qpsSec}, {$qpsAvg}, {$qpsMax}";

        return $status;
    }
}
