<?php

namespace Weipaitang\Framework\ServiceCenter;

/**
 * Class SystemInfo
 * disk calc based on https://github.com/idning/iostat-py
 *
 * @package Weipaitang\Framework\ServiceCenter
 */
class SystemInfo
{
    const SECTOR_SIZE = 512;

    /**
     * @var array
     */
    private $disk = [];

    /**
     * DiskStat constructor.
     */
    public function __construct()
    {
        $partitions = array_slice(array_filter(explode("\n", file_get_contents('/proc/partitions'))), 1);
        foreach ($partitions as &$partition) {
            if (is_numeric(substr($partition, -1, 1))) {
                continue;
            }

            $this->disk[] = array_slice(preg_split('/\s+/', $partition), -1)[0];
        }
        unset($partitions);
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        $info = [];

        $prevDisk = $this->parseDiskStats();
        $prevNet  = $this->parseNet();

        sleep(1);

        $currDisk = $this->parseDiskStats();
        $currNet  = $this->parseNet();

        foreach ($this->disk as $disk) {
            $info['io'][$disk] = $this->calcDisk($prevDisk[$disk], $currDisk[$disk]);
        }
        unset($currDisk, $prevDisk);

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

        return $info;
    }

    /**
     * @return array
     */
    public function parseDiskStats()
    {
        $microtime = microtime(true);

        $data = [];

        $diskstats = array_filter(explode("\n", file_get_contents('/proc/diskstats')));
        foreach ($diskstats as &$diskstat) {
            $diskstat = array_slice(preg_split('/\s+/', $diskstat), 3);

            if (! in_array($diskstat[0], $this->disk)) {
                continue;
            }

            $data[$diskstat[0]] = [
                'r_ios'     => $diskstat[1],
                'r_merges'  => $diskstat[2],
                'r_sec'     => $diskstat[3],
                'r_ticks'   => $diskstat[4],
                'w_ios'     => $diskstat[5],
                'w_merges'  => $diskstat[6],
                'w_sec'     => $diskstat[7],
                'w_ticks'   => $diskstat[8],
                'ios_pgr'   => $diskstat[9],
                'tot_ticks' => $diskstat[10],
                'rq_ticks'  => $diskstat[11],
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
    public function calcDisk(array $last, array $curr)
    {
        $stat = [];

        $diff = function ($field) use ($last, $curr) {
            return ($curr[$field] - $last[$field]) / ($curr['time'] - $last['time']);
        };

        $stat['rrqm/s'] = $diff('r_merges');
        $stat['wrqm/s'] = $diff('w_merges');
        $stat['r/s']    = $diff('r_ios');
        $stat['w/s']    = $diff('w_ios');
        $stat['rkB/s']  = $diff('r_sec') * self::SECTOR_SIZE / 1024;
        $stat['wkB/s']  = $diff('w_sec') * self::SECTOR_SIZE / 1024;

        $stat['avqqu-sz'] = $diff('rq_ticks') / 1000;
        $stat['util']     = $diff('tot_ticks') / 10;

        if (($diff('r_ios') + $diff('w_ios')) > 0) {
            $div = $diff('r_ios') + $diff('w_ios');

            $stat['avgrq-sz'] = ($diff('r_sec') + $diff('w_sec')) / $div;
            $stat['await']    = ($diff('r_ticks') + $diff('w_ticks')) / $div;
            $stat['svctm']    = $diff('tot_ticks') / $div;
        } else {
            $stat['avgrq-sz'] = 0;
            $stat['await']    = 0;
            $stat['svctm']    = 0;
        }

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
        $net = array_slice($net, 2);

        $info = [];
        foreach ($net as $item) {
            $item   = array_slice(preg_split('/\s+/', $item), 1);
            $device = str_replace(':', '', $item[0]);

            $info[$device] = [
                'receive'  => $item[1],
                'transmit' => $item[9]
            ];
        }

        return $info;
    }
}
