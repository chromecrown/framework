<?php

namespace Weipaitang\Framework\ServiceCenter;

use Weipaitang\Framework\Task as AbstractTask;

/**
 * Class Task
 * @package Weipaitang\Framework\ServiceCenter
 */
class Task extends AbstractTask
{
    const SECTOR_SIZE = 512;

    /**
     * @var array
     */
    private $disk = [];

    /**
     * @return array
     */
    public function status()
    {
        $status = [];

        // linux system info
        if (PHP_OS == 'Linux') {
            $status = $this->getInfo();
        } else {
            sleep(1);
        }

        $status['load_avg'] = join(', ', array_map(function ($v) {
            return round($v, 2);
        }, sys_getloadavg()));

        return $status;
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        $this->disk = $this->parsePartitions();

        $info = [];

        $prevIO  = $this->parseIOStats();
        $prevNet = $this->parseNet();

        sleep(1);

        $currIo  = $this->parseIOStats();
        $currNet = $this->parseNet();

        foreach ($this->disk as $disk) {
            $info['io'][$disk] = $this->calcIO($prevIO[$disk], $currIo[$disk]);
        }
        unset($prevIO, $currIo);

        foreach ($prevNet as $device => $net) {
            $receiveSpeed  = $currNet[$device]['receive'] - $net['receive'];
            $transmitSpeed = $currNet[$device]['transmit'] - $net['transmit'];

            $info['net'][$device] = [
                'receive'  => bcdiv($receiveSpeed * 8, 1048576, 2) . 'm/s',
                'transmit' => bcdiv($transmitSpeed * 8, 1048576, 2) . 'm/s'
            ];
        }
        unset($currNet, $prevNet);

        $info['cpu']    = $this->parseCpu(). '%';
        $info['memory'] = $this->parseMemory();
        $info['disk']   = $this->parseDisk();

        return $info;
    }

    /**
     * SystemInfo constructor.
     */
    public function parsePartitions()
    {
        $disk = [];

        $partitions = array_slice(array_filter(explode("\n", file_get_contents('/proc/partitions'))), 1);
        foreach ($partitions as &$partition) {
            if (is_numeric(substr($partition, -1, 1))) {
                continue;
            }

            $disk[] = array_slice(preg_split('/\s+/', $partition), -1)[0];
        }
        unset($partitions);

        return $disk;
    }

    /**
     * @return array
     */
    public function parseIOStats()
    {
        $microtime = microtime(true);

        $data = [];

        $diskstats = array_filter(explode("\n", file_get_contents('/proc/diskstats')));
        foreach ($diskstats as &$diskstat) {
            $diskstat = array_slice(preg_split('/\s+/', trim($diskstat)), 2);

            if (! in_array($diskstat[0], $this->disk)) {
                continue;
            }

            $data[$diskstat[0]] = [
                'r_ios'     => $diskstat[1],
                'r_sec'     => $diskstat[3],
                'r_ticks'   => $diskstat[4],
                'w_ios'     => $diskstat[5],
                'w_sec'     => $diskstat[7],
                'w_ticks'   => $diskstat[8],
                'tot_ticks' => $diskstat[10],
                'time'      => $microtime,
            ];
        }

        return $data;
    }

    /**
     * @param array $last
     * @param array $curr
     * @return array
     */
    public function calcIO(array $last, array $curr)
    {
        $stat = [];

        $diff = function ($field) use ($last, $curr) {
            return $curr[$field] - $last[$field];
        };

        $stat['rkB/s']  = $diff('r_sec') * self::SECTOR_SIZE / 1024;
        $stat['wkB/s']  = $diff('w_sec') * self::SECTOR_SIZE / 1024;

        $div = $diff('r_ios') + $diff('w_ios');
        if ($div > 0) {
            $stat['await'] = ($diff('r_ticks') + $diff('w_ticks')) / $div;
        } else {
            $stat['await'] = 0;
        }

        $stat['util'] = $diff('tot_ticks') / 10;

        return array_map(function ($item) {
            return (float)number_format($item, 3);
        }, $stat);
    }

    /**
     * @return float
     */
    public function parseCpu()
    {
        return 100.0 - (float) trim(shell_exec("vmstat | tail -1 | awk '{print $15}'"));
    }

    /**
     * @return string
     */
    public function parseMemory()
    {
        $memory = file_get_contents('/proc/meminfo');

        preg_match('/MemTotal:\s+(\d+)/', $memory, $match);
        $total = bcdiv((int) $match[1], 1048576, 2);

        preg_match('/MemFree:\s+(\d+)/', $memory, $match);
        $free = bcdiv((int) $match[1], 1048576, 2);

        return ($total - $free) . 'G/'. $total. "G";
    }

    /**
     * @return array
     */
    public function parseNet()
    {
        $net = array_filter(explode("\n", file_get_contents('/proc/net/dev')));
        $net = array_map('trim', array_slice($net, 2));

        $info = [];
        foreach ($net as $item) {
            $item   = preg_split('/\s+/', $item);
            $device = str_replace(':', '', $item[0]);

            $info[$device] = [
                'receive'  => $item[1],
                'transmit' => $item[9]
            ];
        }

        return $info;
    }

    /**
     * @return array
     */
    public function parseDisk()
    {
        $disk = trim(shell_exec('df -m | awk \'{print $1"|"$2"m|"$3"m"}\' | grep "/dev/"'));

        return array_map(function ($item) {
            return explode('|', $item);
        }, explode("\n", $disk));
    }
}
