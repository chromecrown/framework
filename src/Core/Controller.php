<?php

namespace Wpt\Framework\Core;

use Wpt\Framework\Http\Request;
use Wpt\Framework\Http\Response;

/**
 * Class Controller
 *
 * @package Wpt\Framework\Core
 */
abstract class Controller extends Base
{
    /**
     * Tcp 连接标识符
     *
     * @var integer
     */
    protected $fd;

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
     * 设置连接标识符
     *
     * @param int $fd
     */
    public function withFd(int $fd)
    {
        $this->fd = $fd;
    }

    /**
     * @param Request  $request
     * @param Response $response
     */
    public function withHttp(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
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

    /**
     * 析构函数
     */
    public function __destruct()
    {
        unset($this->request, $this->response);
    }
}
