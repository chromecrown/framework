<?php

namespace Weipaitang\Framework;

use Weipaitang\Http\Request;
use Weipaitang\Http\Response;
use Weipaitang\Packet\JsonHandler;
use Weipaitang\Packet\Packet;

/**
 * Class Controller
 * @package Weipaitang\Framework
 */
abstract class Controller
{
    use TraitBase;

    /**
     * @var int
     */
    protected $startTime;

    /**
     * @var int
     */
    protected $fd;

    /**
     * @var int
     */
    protected $fromId;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * status code
     *
     * @var int
     */
    protected $code = 200;

    /**
     * @param int $fd
     *
     * @return $this
     */
    public function withFd(int $fd)
    {
        $this->fd = $fd;

        return $this;
    }

    /**
     * @param int $fd
     */
    public function withFromId(int $fd)
    {
        $this->fromId = $fd;
    }

    /**
     * @param Request $request
     *
     * @return $this
     */
    public function withRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @param Response $response
     *
     * @return $this
     */
    public function withResponse(Response $response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * @param int $code
     * @return $this
     */
    protected function withStatus(int $code = 200)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * tcp|udp only
     *
     * @param array $data
     */
    protected function send(...$data)
    {
        $this->logRunInfo();

        if (! $this->server->exist($this->fd)) {
            return;
        }

        if (count($data) === 1) {
            $data = current($data);
        }

        /**
         * @var Packet $packet
         */
        $packet = $this->container->get('packet');

        $data = $packet->encode(
            $packet->format($data, $this->code)
        );

        $this->server->send($this->fd, $data, $this->fromId);
    }

    /**
     * tcp only
     *
     * @param mixed $data
     * @param bool  $isEnd
     */
    protected function batchSend($data, bool $isEnd = false)
    {
        if ($isEnd) {
            $this->logRunInfo();
        }

        $this->getServer()->batchSend($this->fd, $data, $isEnd, $this->code);
    }

    /**
     * websock only
     *
     * @param array ...$data
     */
    protected function push(...$data)
    {
        $this->logRunInfo();

        if (! $this->server->exist($this->fd)) {
            return;
        }

        if (count($data) === 1) {
            $data = current($data);
        }

        /**
         * @var Packet $packet
         */
        $packet = $this->container->get('packet');

        $data = (new JsonHandler)->pack(
            $packet->format($data, $this->code)
        );

        $this->server->push($this->fd, $data);
    }


    /**
     * http only
     *
     * @param array ...$data
     * @return Response
     */
    protected function response(...$data)
    {
        $this->logRunInfo();

        if (count($data) === 1) {
            $data = current($data);
        }

        $this->response->withStatus($this->code);
        $this->response->withContent(
            (new JsonHandler)->pack($data)
        );

        return $this->response;
    }

    /**
     * 记录运行状态
     */
    protected function logRunInfo()
    {
        /**
         * @var RunInfo $runInfo
         */
        $runInfo = $this->container->get('runinfo');
        $runInfo->logRunInfo(
            $this->code == 200,
            (float)bcsub(microtime(true), $this->startTime, 7)
        );
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        unset($this->request, $this->response);
    }
}
