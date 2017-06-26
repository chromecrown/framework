<?php

namespace Flower\Manage;

use Flower\Utility\Console;
use Flower\Support\Construct;

/**
 * Class Register
 *
 * @package Flower
 */
class Register
{
    use Construct;

    /**
     * @param string $type
     * @return bool
     */
    public function register(string $type = 'register')
    {
        $serverName = $this->server->getServerName();
        $serverIp   = $this->app['config']->get('server_ip', null);
        $serverPort = $this->app['config']->get('tcp_server_port', '9501');
        $serverType = $this->app['config']->get('server_type', 'tcp');
        $serverDesc = $this->app['config']->get('server_desc', '');

        return $this->send($type, [
            'name' => $serverName,
            'ip'   => $serverIp,
            'port' => $serverPort,
            'type' => $serverType,
            'desc' => $serverDesc,
        ]);
    }

    /**
     * @return bool
     */
    public function unregister()
    {
        return $this->register('unregister');
    }

    /**
     * @param string $type
     * @param array  $data
     * @return bool|null
     */
    protected function send(string $type, array $data)
    {
        $registerIp = $this->app['config']->get('service_center_ip', null);
        if (! $registerIp) {
            Console::write('Service center ip not set.', 'red');
            exit(1);
        }

        $registerPort = $this->app['config']->get('service_center_port', '9501');

        try {
            $data = $this->app->get('client.tcp.sync', [
                'set'    => $this->server->getConfig(),
                'config' => [
                    'host'    => $registerIp,
                    'port'    => $registerPort,
                    'timeout' => 1,
                ],
            ])->request([
                'type'    => 'api',
                'request' => 'service',
                'method'  => $type,
                'args'    => $data,
            ]);

            if (! $data) {
                return false;
            }

            return $data ?: false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
