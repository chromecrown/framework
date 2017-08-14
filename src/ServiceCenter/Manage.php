<?php

namespace Weipaitang\Framework\ServiceCenter;

use Weipaitang\Client\Async\Pool\ManagePool;
use Weipaitang\Config\ConfigInterface;
use Weipaitang\Framework\Application;
use Weipaitang\Framework\Controller;
use Weipaitang\Framework\RunInfo;
use Swoole\Server as SwooleServer;
use Weipaitang\Packet\MsgpackHandler;
use Weipaitang\Server\Server;

/**
 * Class Manage
 * @package Weipaitang\Framework\ServiceCenter
 */
class Manage extends Controller
{
    /**
     * @param array $data
     */
    public function dispatch(array $data)
    {
        $this->withStartTime(0.0);

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
        $this->getServer()->task(
            [
                'request' => 'manage.task',
                'method'  => 'status',
            ],
            -1,
            function (SwooleServer $serv, $taskId, $data) {
                $this->send(array_merge(
                    (new MsgpackHandler)->unpack($data),
                    $this->getStatusInfo()
                ));
            }
        );
    }

    /**
     * @return array
     */
    private function getStatusInfo()
    {
        $status = [];

        $status['php_version']       = PHP_VERSION;
        $status['swoole_version']    = SWOOLE_VERSION;
        $status['framework_version'] = Application::VERSION;

        /**
         * @var ManagePool $pool
         */
//        $pool = $this->container->get('pool');
//        $status['pool'] = $pool->getStatusInfo();
//        unset($pool);

        /**
         * @var Server $server
         */
        $server = $this->container->get('server');
        $set    = $server->getServerSet();
        $status['worker_num']      = $set['worker_num'];
        $status['task_worker_num'] = $set['task_worker_num'];
        $status['service_name']    = $server->getServerName();
        unset($set, $server);

        $status = array_merge($status, $this->getServer()->stats());
        unset($status['worker_request_count']);

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
