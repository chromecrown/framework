<?php

namespace Weipaitang\Framework\ServiceCenter;

use Weipaitang\Config\ConfigInterface;
use Weipaitang\Framework\Controller;
use Weipaitang\Framework\RunInfo;
use Swoole\Server as SwooleServer;
use Weipaitang\Packet\MsgpackHandler;

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
                $this->send((new MsgpackHandler)->unpack($data));
            }
        );
    }
}
