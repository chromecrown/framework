<?php

namespace Weipaitang\Framework\ServiceCenter;

use Swoole\Server as SwooleServer;

class Manage
{
    public function withServer(SwooleServer $server)
    {
        return $this;
    }

    public function withFd(int $fd)
    {
        return $this;
    }

    public function dispatch(array $data)
    {

    }
}
