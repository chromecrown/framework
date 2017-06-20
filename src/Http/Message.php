<?php

namespace Flower\Http;

/**
 * Class Message
 * @package Flower\Http
 */
class Message
{
    /**
     * @var string
     */
    private $content = '';

    /**
     * @var array
     */
    private $headers = [];

    /**
     * @var string
     */
    private $protocolVersion = '1.1';

    /**
     * @return string
     */
    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    /**
     * @param $version
     *
     * @return $this
     */
    public function withProtocolVersion($version)
    {
        $this->protocolVersion = $version;

        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasHeader($name)
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * @param      $name
     * @param null $default
     *
     * @return mixed|null
     */
    public function getHeader($name, $default = null)
    {
        return $this->hasHeader($name)
            ? $this->headers[strtolower($name)]
            : $default;
    }

    /**
     * @param $name
     *
     * @return null|string
     */
    public function getHeaderLine($name)
    {
        $value = $this->getHeader($name);

        return $value ? implode(',', $value) : null;
    }

    /**
     * @param $name
     * @param $value
     *
     * @return $this
     */
    public function withHeader($name, $value)
    {
        $this->headers[strtolower($name)] = $value;

        return $this;
    }

    /**
     * @param $name
     * @param $value
     *
     * @return $this
     */
    public function withAddedHeader($name, $value)
    {
        $this->headers[strtolower($name)][] = $value;

        return $this;
    }

    /**
     * @param $name
     *
     * @return $this
     */
    public function withoutHeader($name)
    {
        $name = strtolower($name);

        if ($this->hasHeader($name)) {
            unset($this->headers[$name]);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param $content
     *
     * @return $this
     */
    public function withContent($content)
    {
        $this->content .= $content;

        return $this;
    }
}
