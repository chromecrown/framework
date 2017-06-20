<?php

namespace Flower\Http;

use Swoole\Http\Request as SwooleHttpRequest;

class Request extends Message
{
    /**
     * @var array
     */
    private $bodyParams;

    /**
     * @var array
     */
    private $queryParams;

    /**
     * @var array
     */
    private $serverParams;

    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $uri;

    /**
     * Request constructor.
     *
     * @param SwooleHttpRequest $request
     */
    public function __construct(SwooleHttpRequest $request)
    {
        $this->withBodyParams($request->post ?? []);
        $this->withQueryParams($request->get ?? []);

        $this->withMethod($request->server['request_method']);
        $this->withUri(preg_replace('/\/+/', '/', $request->server['request_uri'] ?: '/'));

        foreach ($request->header as $name => $value) {
            $name = str_replace('-', '_', $name);

            is_array($value)
                ? $this->withAddedHeader($name, $value)
                : $this->withHeader($name, $value);
        }

        $host = '::1';
        foreach (['host', 'server_addr'] as $name) {
            if (isset($request->header[$name])) {
                $host = parse_url($request->header[$name], PHP_URL_HOST) ?: $request->header[$name];
            }
        }

        $this->withServerParams([
            'REQUEST_METHOD'       => $request->server['request_method'],
            'REQUEST_URI'          => $request->server['request_uri'],
            'PATH_INFO'            => $request->server['path_info'],
            'REQUEST_TIME'         => $request->server['request_time'],
            'SERVER_PROTOCOL'      => $request->server['server_protocol'],
            'REQUEST_SCHEMA'       => explode('/', $request->server['server_protocol'])[0],
            'SERVER_NAME'          => $request->header['server_name'] ?? $host,
            'SERVER_ADDR'          => $host,
            'SERVER_PORT'          => $request->server['server_port'],
            'REMOTE_ADDR'          => $request->server['remote_addr'],
            'REMOTE_PORT'          => $request->server['remote_port'],
            'QUERY_STRING'         => $request->server['query_string'] ?? '',
            'HTTP_HOST'            => $host,
            'HTTP_USER_AGENT'      => $request->header['user-agent'] ?? '',
            'HTTP_ACCEPT'          => $request->header['accept'] ?? '*/*',
            'HTTP_ACCEPT_LANGUAGE' => $request->header['accept-language'] ?? '',
            'HTTP_ACCEPT_ENCODING' => $request->header['accept-encoding'] ?? '',
            'HTTP_CONNECTION'      => $request->header['connection'] ?? '',
            'HTTP_CACHE_CONTROL'   => $request->header['cache-control'] ?? '',
        ]);
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param $method
     *
     * @return $this
     */
    public function withMethod($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @param $uri
     *
     * @return $this
     */
    public function withUri($uri)
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * @param      $name
     * @param null $default
     *
     * @return mixed|null
     */
    public function getServerParam($name, $default = null)
    {
        return $this->serverParams[$name] ?? $default;
    }

    /**
     * @return array
     */
    public function getServerParams()
    {
        return $this->serverParams;
    }

    /**
     * @param array $server
     *
     * @return $this
     */
    public function withServerParams(array $server)
    {
        $this->serverParams = $server;

        return $this;
    }

    /**
     * @param      $name
     * @param null $default
     *
     * @return mixed|null
     */
    public function getBodyParam($name, $default = null)
    {
        return $this->bodyParams[$name] ?? $default;
    }

    /**
     * @return array
     */
    public function getBodyParams()
    {
        return $this->bodyParams;
    }

    /**
     * @param $params
     *
     * @return $this
     */
    public function withBodyParams($params)
    {
        $this->bodyParams = $params;

        return $this;
    }

    /**
     * @param      $name
     * @param null $default
     *
     * @return mixed|null
     */
    public function getQueryParam($name, $default = null)
    {
        return $this->queryParams[$name] ?? $default;
    }

    /**
     * @return array
     */
    public function getQueryParams()
    {
        return $this->queryParams;
    }

    /**
     * @param $params
     *
     * @return $this
     */
    public function withQueryParams($params)
    {
        while (list($key, $value) = each($params)) {
            $this->queryParams[$key] = $value;
        }

        return $this;
    }

    /**
     * @param      $name
     * @param null $default
     *
     * @return mixed|null
     */
    public function getRequest($name, $default = null)
    {
        if (isset($this->queryParams[$name])) {
            return $this->queryParams[$name];
        }

        if (isset($this->bodyParams[$name])) {
            return $this->bodyParams[$name];
        }

        return $default;
    }
}
