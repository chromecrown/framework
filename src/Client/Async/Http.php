<?php

namespace Wpt\Framework\Client\Async;

use Swoole\Http\Client as SwooleHttpClient;

/**
 * Class Http
 *
 * @package Wpt\Framework\Client\Async
 *
 * @method HTTP set(array $set)
 * @method HTTP setHeaders(array $headers)
 * @method HTTP setCookies(array $cookies)
 * @method HTTP addFile(string $path, string $name, string $filename = null, string $mimeType = null, int $offset = 0, int $length)
 */
class Http extends Base
{
    /**
     * @var string
     */
    private $url;

    /**
     * @var array|string
     */
    private $data;

    /**
     * @var string
     */
    private $method;

    /**
     * @var SwooleHttpClient
     */
    private $client;

    /**
     * Http constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->timeout = $config['timeout'] ?? 10;
        $this->client  = new SwooleHttpClient($config['host'], $config['port']);
        unset($config);
    }

    /**
     * @param string       $url
     * @param array|string $data
     * @param string       $method
     * @return \Generator
     */
    public function request(string $url, $data, string $method = 'get')
    {
        $this->url  = $url;
        $this->data = $data;

        $this->setMethod($method);

        return yield $this;
    }

    /**
     * @param callable     $callback
     * @param string       $url
     * @param array|string $data
     * @param string       $method
     */
    public function call(callable $callback, string $url, $data, string $method = 'get')
    {
        $this->url = $url;
        $this->data = $data;

        $this->setMethod($method);

        $this->send($callback);
    }

    /**
     * @param string $method
     *
     * @return $this
     */
    public function setMethod(string $method)
    {
        $this->method = strtolower($method);

        return $this;
    }

    /**
     * @param callable $callback
     */
    public function send(callable $callback)
    {
        $this->callback = $callback;
        unset($callback);

        $this->startTick();

        $callback = function (SwooleHttpClient $client) {
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

        if (! in_array($this->method, ['get', 'post'])) {
            $this->client->setMethod(strtoupper($this->method));
            if ($this->data) {
                $this->client->setData($this->data);
            }

            $this->client->execute($this->url, $callback);

            return;
        }

        $arguments = [];
        if ($this->method == 'get') {
            if ($this->data) {
                $this->url .= (strpos($this->url, '?') === false) ? '?' : '&';
                $this->url .= http_build_query($this->data);
            }
        } else {
            $arguments[] = $this->data;
        }

        $arguments[] = $callback;

        array_unshift($arguments, $this->url);

        $this->client->{$this->method}(...$arguments);
    }

    /**
     * @param string $name
     * @param array  $arguments
     * @return $this
     * @throws \Exception
     */
    public function __call(string $name, array $arguments)
    {
        if (! in_array($name, ['set', 'setHeaders', 'setCookies', 'addFile'])) {
            throw new \Exception('HttpClient 方法不存在: '. $name);
        }

        $this->client->$name(...$arguments);

        return $this;
    }
}

