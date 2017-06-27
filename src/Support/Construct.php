<?php

namespace Wpt\Framework\Support;

use Wpt\Framework\Server\Server;
use Wpt\Framework\Core\Application;

trait Construct
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Server
     */
    protected $server;

    /**
     * constructor.
     *
     * @param Application $app
     * @param Server      $server
     */
    public function __construct(Application $app, Server $server)
    {
        $this->app = $app;
        $this->server = $server;
    }
}
