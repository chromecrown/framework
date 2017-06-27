<?php

namespace Wpt\Framework\Client\Async;

use Swoole\Async as SwooleAsync;

/**
 * Class Dns
 * @package Wpt\Framework\Client\Async
 */
class Dns extends Base
{
    private $host;

    /**
     * @param string $host
     *
     * @return \Generator
     */
    public function query(string $host)
    {
        $this->host = $host;

        return yield $this;
    }

    /**
     * @param callable $callback
     */
    public function send(callable $callback)
    {
        $host = $this->host;

        $port = null;
        if (strpos($host, ':')) {
            list($host, $port) = explode(':', $host);
        }

        $isDomain = is_numeric(str_replace('.', '', $host));

        if (! $isDomain) {
            $callback($this->host);
            return;
        }

        $this->callback = $callback;

        $this->startTick();

        SwooleAsync::dnsLookup($this->host, function ($domain, $ip, $port) {
            $this->clearTick();

            if ($this->callback) {
                ($this->callback)($ip . ':' . $port);
            }
        });
    }
}

