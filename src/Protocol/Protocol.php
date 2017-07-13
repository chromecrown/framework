<?php

namespace Weipaitan\Framework\Protocol;

use Weipaitang\Console\Output;
use Weipaitang\Framework\Application;
use Weipaitang\Framework\RunInfo;
use Weipaitang\Server\Server;

/**
 * Class Protocol
 * @package Weipaitan\Framework\Protocol
 */
abstract class Protocol
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

    /**
     * @param Application $app
     *
     * @return $this
     */
    public function withContainer(Application $app)
    {
        $this->app = $app;

        return $this;
    }

    /**
     * @param bool  $status
     * @param float $useTime
     *
     * @return $this
     */
    protected function logRunInfo(bool $status = true, float $useTime = 0.0)
    {
        /**
         * @var RunInfo $runInfo
         */
        $runInfo = $this->app->get('runinfo');
        $runInfo->logRunInfo($status, $useTime);

        return $this;
    }

    /**
     * @param string $type
     * @param string $request
     * @param string $method
     * @param array  $params
     */
    protected function logRequest(string $type, string $request, string $method, array &$params = [])
    {
        if (! DEBUG_MODEL) {
            return;
        }

        $message = strtoupper($type)
            . ' '
            . $request
            . ':'
            . $method
            . '('
            . substr(json_encode(array_values($params)), 1, -1)
            . ')';

        Output::debug($message, 'blue');
    }
}
