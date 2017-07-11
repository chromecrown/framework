<?php

namespace Weipaitan\Framework\Protocol;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Weipaitang\Server\Server;
use Swoole\WebSocket\Server as SwooleWebSocketServer;
use Swoole\WebSocket\Frame as SwooleWebSocketFrame;

class WebSocket extends Tcp
{
    public function register()
    {
        $this->server->hook(Server::ON_HANDSHAKE, [$this, 'onHandShake']);
        $this->server->hook(Server::ON_OPEN,      [$this, 'onOpen']);
        $this->server->hook(Server::ON_MESSAGE,   [$this, 'onMessage']);
        $this->server->hook(Server::ON_CLOSE,     [$this, 'onClose']);
    }

    public function onHandShake(Request $request, Response $response)
    {

    }

    public function onOpen(SwooleWebSocketServer $server, Request $request)
    {

    }

    public function onMessage(SwooleWebSocketServer $server, SwooleWebSocketFrame $frame)
    {

    }
}
