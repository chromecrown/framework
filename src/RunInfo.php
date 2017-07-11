<?php

namespace Weipaitang\Framework;

use Swoole\Server;
use Swoole\Table as SwooleTable;

/**
 * Class RunInfo
 * @package Weipaitang\Framework
 */
class RunInfo
{
    /**
     * @var SwooleTable
     */
    private $table;

    /**
     * RunInfo constructor.
     */
    public function __construct()
    {
        $table = new SwooleTable(50);
        $table->column('success', SwooleTable::TYPE_INT, 8);
        $table->column('failure', SwooleTable::TYPE_INT, 8);
        $table->column('time',    SwooleTable::TYPE_FLOAT, 8);
        $table->create();

        $this->table = $table;
    }

    /**
     * @return SwooleTable
     */
    public function getRunTable()
    {
        return $this->table;
    }

    /**
     * 记录运行信息
     *
     * @param bool  $status
     * @param float $runTime
     */
    public function logRunInfo(bool $status = true, float $runTime = 0.0)
    {
        $this->table->incr('total', $status ? 'success' : 'failure', 1);
        $this->table->incr('total', 'time', $runTime);

        $this->table->incr('qps_' . \time(), 'success', 1);
    }

    /**
     * 记录最大 QPS
     *
     * @param $worker
     */
    protected function logMaxQps($worker)
    {
        /**
         * @var Server $worker
         */
        $worker->tick(
            1000,
            function () {
                $time = \time();
                $prevSec = $this->table->get('qps_' . ($time - 1));
                if (! $prevSec) {
                    return;
                }

                // 删除前10~15秒记录
                $except = ['qps_max' => true];
                for ($i = 0; $i < 5; $i++) {
                    $except['qps_' . ($time - $i)] = true;
                }

                foreach ($this->table as $k => $v) {
                    if (strpos($k, 'qps_') !== false and ! isset($except[$k])) {
                        $this->table->del($k);
                    }
                }
                unset($except);

                // qps max
                $needAdd = true;
                if ($qpsMax = $this->table->get('qps_max')) {
                    if ($qpsMax['success'] < $prevSec['success']) {
                        $this->table->del('qps_max');
                    } else {
                        $needAdd = false;
                    }
                }

                if (! $needAdd) {
                    return;
                }

                $this->table->set('qps_max', [
                    'time'    => 0,
                    'success' => $prevSec['success'],
                    'failure' => 0,
                ]);
            }
        );
    }
}
