<?php

namespace Weipaitan\Framework\Protocol;

use Weipaitang\Server\Server;
use Swoole\Server as SwooleServer;

class Udp extends Protocol
{
    public function register()
    {
        $this->server->hook(Server::ON_PACKET, [$this, 'onPacket']);
    }

    public function onPacket(SwooleServer $server, string $data, array $clientInfo)
    {

    }
}
