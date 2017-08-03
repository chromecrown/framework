<?php

namespace Weipaitang\Framework\ServiceCenter;

use Swoole\Client;
use Weipaitang\Config\ConfigInterface;
use Weipaitang\Console\Output;
use Weipaitang\Framework\Controller;
use Weipaitang\Packet\Packet;
use Weipaitang\Server\Server;

/**
 * Class Register
 * @package Weipaitang\Framework\Manage
 */
class Register extends Controller
{
    /**
     * @param string $type
     * @return bool
     */
    public function register(string $type = 'register')
    {
        /**
         * @var Server $server
         */
        $server = $this->container->get('server');
        $serverName = $server->getServerName();
        $serverPort = join(',', $server->getServerPorts());

        /**
         * @var ConfigInterface $config
         */
        $config = $this->container->get('config');

        $serverIp   = $config->get('server_ip', '');
        if (! $serverIp) {
            $serverIp = current(swoole_get_local_ip());
        }

        $serverType = $config->get('server_type', 1);
        $serverDesc = $config->get('server_desc', '');

        return $this->sendToServiceCenter($type, [
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
    protected function sendToServiceCenter(string $type, array $data)
    {
        /**
         * @var ConfigInterface $config
         */
        $config = $this->container->get('config');

        $registerIp = $config->get('service_center.ip', null);
        if (! $registerIp) {
            Output::write('Service center ip not set.', 'red');
            exit(1);
        }

        $registerPort = $config->get('service_center.port', '9501');
        unset($config);

        /**
         * @var Server $server
         */
        $server = $this->container->get('server');
        $serverSet = $server->getServerSet();
        unset($server);

        /**
         * @var Packet $packet
         */
        $packet = $this->container->get('packet');
        $data = $packet->encode(
            $packet->format([
                'type'    => 'api',
                'request' => 'server',
                'method'  => $type,
                'args'    => $data,
            ])
        );
        unset($packet);

        try {
            $client = new Client(SWOOLE_SOCK_TCP);
            $client->set($serverSet);
            $client->connect($registerIp, $registerPort, 1);
            $client->send($data);
            $result = $client->recv();

            return $result ?: false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
