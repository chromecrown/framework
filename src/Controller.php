<?php

namespace Weipaitang\Framework;

use Weipaitang\Http\Request;
use Weipaitang\Http\Response;
use Swoole\Server as SwooleServer;

/**
 * Class Controller
 *
 * @package Weipaitang\Framework
 */
abstract class Controller extends Base
{
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

    public function withServer(SwooleServer $server)
    {
        $this->server = $server;

        return $this;
    }

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
     * 发送消息 (TCP ONLY)
     *
     * @param array $data
     */
    protected function send(...$data)
    {
        $this->logRunInfo();

        if (count($data) === 1) {
            $data = current($data);
        }

        $this->getServer()->send($this->fd, $data, $this->code);
    }

    /**
     * 批量发送消息，用于大数据量 (TCP ONLY)
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
     * 发送消息 (HTTP ONLY)
     *
     * @param        $data
     * @param string $msg
     * @param int    $code
     *
     * @return Response
     */
    protected function response($data = null, $msg = '', $code = 1)
    {
        $this->logRunInfo();

        $this->response->withStatus($this->code);
        $this->response->withContent(json_encode([
            'code' => $code,
            'msg'  => $msg,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE));

        return $this->response;
    }

    /**
     * 记录运行状态
     */
    protected function logRunInfo()
    {
        $this->app->logRunInfo($this->code == 200, (float)bcsub(microtime(true), $this->startTime, 7));
    }

    /**
     * 获取输入参数 (HTTP ONLY)
     *
     * @param  string $name
     * @param  mixed  $default
     * @return null
     */
    protected function input(string $name, $default = null)
    {
        return $this->request->getRequest($name, $default);
    }

//    /**
//     * for tcp
//     *
//     * @param int   $fd
//     * @param mixed $data
//     * @param int   $code
//     */
//    public function sendsss(int $fd, $data, int $code = 200)
//    {
//        if (! $this->server->exist($fd)) {
//            return;
//        }
//
//        $data = $this->packet->encode(
//            $this->packet->format($data, $code),
//            $this->serverSet['package_eof']
//        );
//
//        if (mb_strlen($data) > 1024 * 1024) {
//            $data = str_split($data, 1024 * 1024);
//        } else {
//            $data = [$data];
//        }
//
//        foreach ($data as $v) {
//            $this->server->send($fd, $v);
//        }
//    }
//
//    /**
//     * for udp
//     *
//     * @param string $host
//     * @param int    $port
//     * @param mixed  $data
//     * @param int    $code
//     */
//    public function sendto(string $host, int $port, $data, int $code = 200)
//    {
//        $this->server->sendto($host, $port, $this->packet->encode(
//            $this->packet->format($data, $code),
//            $this->serverSet['package_eof']
//        ));
//    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        unset($this->request, $this->response);
    }
}
