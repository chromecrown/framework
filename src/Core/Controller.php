<?php

namespace Flower\Core;

use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Class Controller
 *
 * @package Flower\Core
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
    public function setFd(int $fd)
    {
        $this->fd = $fd;
    }

    /**
     * @param Request  $request
     * @param Response $response
     */
    public function setHttp(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * @param int $code
     * @return $this
     */
    protected function setStatus(int $code = 200)
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
     * @param mixed $data
     * @param int   $code
     */
    protected function response($data, int $code = 200)
    {
        if (! is_string($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        $this->logRunInfo();

        $this->response->status($code);
        $this->response->end($data);
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
        return $this->request->request[$name] ?? $default;
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        unset($this->request, $this->response);
    }
}
