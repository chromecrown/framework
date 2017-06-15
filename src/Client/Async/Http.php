<?php

namespace Flower\Client\Async;

use Flower\Coroutine\CoroutineInterface;
use Swoole\Http\Client as SwooleHttpClient;

/**
 * Class Http
 *
 * @package Flower\Client\Async
 */
class Http implements CoroutineInterface
{
    private $timer;
    private $timeout;
    private $url;
    private $data;
    private $method;

    /**
     * @var callable
     */
    private $callback;

    /**
     * @var SwooleHttpClient
     */
    private $connect;

    /**
     * Tcp constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->timeout = $config['timeout'] ?? 10;
        $this->connect = new SwooleHttpClient($config['host'], $config['port']);
        unset($config);
    }

    /**
     * @param string $url
     * @param array  $data
     * @return \Generator
     */
    public function post(string $url, array $data)
    {
        return $this->request($url, $data, 'post');
    }

    /**
     * @param string $url
     * @param array  $data
     * @return \Generator
     */
    public function get(string $url, array $data)
    {
        return $this->request($url, $data, 'get');
    }

    /**
     * @param string $url
     * @param array  $data
     * @param string $method
     * @return \Generator
     */
    public function request(string $url, array $data, string $method = 'get')
    {
        $this->url = $url;
        $this->data = $data;
        $this->method = strtolower($method);

        return yield $this;
    }

    /**
     * @param callable $callback
     */
    public function send(callable $callback)
    {
        $this->callback = $callback;

        $this->startTick();

        $arguments = [];
        if ($this->method == 'get') {
            if ($this->data) {
                $this->url .= (strpos($this->url, '?') === false) ? '?' : '&';
                $this->url .= http_build_query($this->data);
            }
        } else {
            $arguments[] = $this->data;
        }

        $arguments[] = function (SwooleHttpClient $client) {
            $this->clearTick();

            if ($this->callback) {
                ($this->callback)([
                    'status' => $client->statusCode,
                    'header' => $client->headers ?? [],
                    'body'   => $client->body ?? null,
                    'cookie' => $client->set_cookie_headers ?? [],
                ]);
            }

            if ($client->isConnected()) {
                $client->close();
            }
        };

        array_unshift($arguments, $this->url);

        $this->connect->{$this->method}(...$arguments);
    }

    /**
     * 超时计时器
     */
    private function startTick()
    {
        $this->timer = swoole_timer_after(
            floatval($this->timeout) * 1000,
            function () {
                $callback = $this->callback;

                $this->callback = null;

                $callback(null);
            }
        );
    }

    /**
     * clear tick
     */
    private function clearTick()
    {
        if ($this->timer) {
            swoole_timer_clear($this->timer);
        }

        // reset
        $this->timer = null;
    }

    /**
     * @param string $name
     * @param array  $arguments
     * @return $this
     * @throws \Exception
     */
    public function __call(string $name, array $arguments)
    {
        if (! in_array($name, ['setHeaders', 'setCookies', 'setData', 'addFile'])) {
            throw new \Exception('方法不存在');
        }

        $this->connect->$name(...$arguments);

        return $this;
    }
}

