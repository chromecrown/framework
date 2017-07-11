<?php

namespace Weipaitan\Framework\Protocol;

use Weipaitang\Server\Server;

/**
 * Class Protocol
 * @package Weipaitan\Framework\Protocol
 */
abstract class Protocol
{
    /**
     * @var Server
     */
    protected $server;

    /**
     * @return void
     */
    abstract function register();

    /**
     * @param Server $server
     */
    public function withServer(Server $server)
    {
        $this->server = $server;
    }
}
