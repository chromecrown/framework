<?php

namespace Flower\Client\Async;

use Flower\Log\Log;
use Flower\Core\Packet;
use Flower\Core\Application;
use Flower\Client\Tcp as TcpClient;

/**
 * Class TcpClient
 *
 * @package App\Library
 */
class Tcp extends Base
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @var Packet
     */
    private $packet;

    /**
     * @var mixed
     */
    private $data;

    /**
     * @var boolean
     */
    private $format;

    /**
     * @var array
     */
    private $config;

    /**
     * @var array
     */
    private $set = [
        'open_eof_check' => 1,
        'open_eof_split' => 1,
        'package_eof'    => "#\r\n\r\n",

        'package_max_length' => 1024 * 1024 * 2,
        'open_tcp_nodelay'   => 1,
    ];

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

        $this->set    = array_merge($this->set, $config['set'] ?? []);
        $this->config = $config['config'];
        unset($config);
    }

    /**
     * @param callable $callback
     * @param mixed    $data
     * @param bool     $format
     */
    public function call(callable $callback, $data, bool $format = true)
    {
        $this->data = $data;
        $this->format = $format;

        $this->send($callback);
    }

    /**
     * @param      $data
     * @param bool $format
     * @return \Generator
     */
    public function request($data, bool $format = true)
    {
        $this->data = $data;
        $this->format = $format;

        return yield $this;
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
                $result = $this->format
                    ? $this->packet->decode($result, $this->set['package_eof'])
                    : $result;

                ($this->callback)($result);
            }
        });
    }

    /**
     * @param TcpClient $client
     */
    public function close(TcpClient $client)
    {
        if (isset($client->errCode)) {
            Log::error($client->errCode);
        }
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
