<?php

namespace Wpt\Framework\Client\Async;

use Wpt\Framework\Core\Packet;
use Wpt\Framework\Core\Application;
use Wpt\Framework\Client\Tcp as TcpClient;
use Wpt\Framework\Support\AsyncTcpTrait;

/**
 * Class TcpClient
 *
 * @package App\Library
 */
class Tcp extends Base
{
    use AsyncTcpTrait;

    /**
     * @var array
     */
    protected $config;

    /**
     * TcpClient constructor.
     *
     * @param Application $app
     * @param Packet      $packet
     * @param array       $config
     */
    public function __construct(Application $app, Packet $packet, array $config)
    {
        $this->app    = $app;
        $this->packet = $packet;

        $this->config = $config['config'];
        $this->setSet($config['set'] ?? []);
        unset($config);
    }

    /**
     * @param callable $callback
     */
    public function send(callable $callback)
    {
        $this->callback = $callback;

        /**
         * @var TcpClient $client
         */
        $client = $this->app->get('client.tcp');

        $client->on('close', [$this, 'close']);
        $client->on('connect', [$this, 'connect']);

        $client->connect($this->config['host'], $this->config['port'], $this->set, $this->config['timeout'] ?? 3);
    }

    /**
     * @param TcpClient $client
     * @param           $result
     */
    public function connect(TcpClient $client, $result)
    {
        if (! $result) {
            $this->failure('Tcp connect failure', -1);

            return;
        }

        $request = $this->format
            ? $this->packet->encode($this->data, $this->set['package_eof'])
            : $this->data;

        $this->startTick($client);

        $client->send($request, function (TcpClient $client, $result) {
            $this->clearTick();
            if ($this->callback) {
                if ($this->format) {
                    list($result, ) = $this->parseResult($result);
                }

                ($this->callback)($result);
            }
        });
    }

    /**
     * @param     $data
     * @param int $code
     */
    private function failure($data, $code = -1)
    {
        $callback = $this->callback;
        unset($this->callback);

        $callback(
            $this->format
                ? $this->packet->format($data, $code)
                : null
        );
    }

    /**
     * @param TcpClient $client
     */
    protected function startTick($client = null)
    {
        $this->timer = swoole_timer_after(
            floatval($this->config['timeout']) * 1000,
            function () use ($client) {
                $client->close();
                $this->failure('Tcp timeout', -2);
            }
        );
    }
}
