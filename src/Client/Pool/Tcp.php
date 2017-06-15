<?php

namespace Flower\Client\Pool;

use Flower\Log\Log;
use Flower\Pool\Pool;
use Flower\Core\Packet;
use Flower\Coroutine\CoroutineInterface;
use Flower\Core\Application;
use Flower\Client\Tcp as TcpClient;

/**
 * Class TcpPool
 *
 * @package Flower\Client\Pool
 */
class Tcp extends Pool implements CoroutineInterface
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
     * @var array
     */
    private $request;

    /**
     * @var boolean
     */
    private $format;

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
     * Tcp constructor.
     *
     * @param Application $app
     * @param Packet      $packet
     * @param string      $name
     * @param array       $config
     * @throws \Exception
     */
    public function __construct(Application $app, Packet $packet, string $name, array $config = [])
    {
        $this->type = 'tcp';
        $this->name = $name;
        $this->app = $app;
        $this->packet = $packet;

        if (! isset($config['config'])) {
            throw new \Exception('Tcp connect config not found.');
        }

        $this->config = $config['config'];
        $this->set = array_merge($this->set, $config['set'] ?? []);
        unset($config);

        parent::__construct();
    }

    /**
     * @param callable $callback
     * @param string   $request
     * @param bool     $format
     */
    public function call(callable $callback, string $request, bool $format = true)
    {
        $this->request = $request;
        $this->format = $format;

        $this->send($callback);
    }

    /**
     * @param mixed $data
     * @param bool  $format
     * @return \Generator
     */
    public function request($data, bool $format = true)
    {
        $this->request = $data;
        $this->format = $format;

        return yield $this;
    }

    /**
     * @param callable $callback
     */
    public function send(callable $callback)
    {
        $request = $this->format
            ? $this->packet->encode($this->packet->format($this->request), $this->set['package_eof'])
            : $this->request;

        $data = [
            'request' => $request,
            'token'   => $this->getToken($callback, true),
            'retry'   => 0,
            'format'  => $this->format,
        ];

        $this->request = $this->format = null;

        $this->execute($data);
    }

    /**
     * @param array $data
     */
    public function execute(array $data)
    {
        $client = $this->getConnection();
        if (! $client) {
            $this->retry($data);

            return;
        }

        $client->send($data['request'], function (TcpClient $client, $result) use (&$data) {
            $this->release($client);

            if ($data['format']) {
                $result = $this->packet->decode($result, $this->set['package_eof']);
            }

            $this->callback($data['token'], $result);
            unset($data);
        });
    }

    /**
     * connect tcp
     */
    public function connect()
    {
        $this->waitConnect++;

        $client = $this->app->get('client.tcp');
        $client->on('close', [$this, 'close']);
        $client->connect(
            $this->config['host'],
            $this->config['port'],
            $this->set,
            $this->getTimeout(),
            function (TcpClient $client, $result) {
                $this->waitConnect--;

                if (! $result) {
                    $this->close($client);

                    return;
                }

                $this->currConnect++;
                $this->release($client);
            }
        );
    }

    /**
     * @param TcpClient $client
     */
    public function close(TcpClient $client)
    {
        if (isset($client->errCode)) {
            Log::error($client->errCode);
        }

        $this->release($client);
    }
}
