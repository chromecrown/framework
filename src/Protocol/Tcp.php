<?php

namespace Weipaitan\Framework\Protocol;

use Weipaitang\Server\Server;
use Swoole\Server as SwooleServer;

class Tcp extends Protocol
{
    public function register()
    {
        $this->server->withHook(Server::ON_CONNECT, [$this, 'onConnect']);
        $this->server->withHook(Server::ON_RECEIVE, [$this, 'onReceive']);
        $this->server->withHook(Server::ON_CLOSE,   [$this, 'onClose']);
    }

    public function onConnect(SwooleServer $server, int $fd, int $fromId)
    {

    }

    public function onReceive(SwooleServer $server, int $fd, int $fromId, string $data)
    {

    }

    public function onClose(SwooleServer $server, int $fd, int $fromId)
    {

    }
}
