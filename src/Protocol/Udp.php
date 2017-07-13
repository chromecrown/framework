<?php

namespace Weipaitan\Framework\Protocol;

use Weipaitang\Server\Server;
use Swoole\Server as SwooleServer;

/**
 * Class Udp
 * @package Weipaitan\Framework\Protocol
 */
class Udp extends Tcp
{
    /**
     * @var string
     */
    protected $type = 'Udp';

    /**
     * @return void
     */
    public function register()
    {
        $this->server->hook(Server::ON_PACKET, [$this, 'onPacket']);
    }

    /**
     * @param SwooleServer $server
     * @param string       $data
     * @param array        $clientInfo
     */
    public function onPacket(SwooleServer $server, string $data, array $clientInfo)
    {
        $fd     = unpack('L', pack('N', ip2long($clientInfo['address'])))[1];
        $fromId = ($clientInfo['server_socket'] << 16) + $clientInfo['port'];

        $this->dispatch($server, $fd, $fromId, $data);
    }
}
