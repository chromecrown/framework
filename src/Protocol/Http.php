<?php

namespace Weipaitan\Framework\Protocol;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Weipaitang\Server\Server;

class Http extends Protocol
{
    public function register()
    {
        $this->server->withHook(Server::ON_REQUEST, [$this, 'onRequest']);
    }

    public function onRequest(Request $request, Response $response)
    {

    }
}
