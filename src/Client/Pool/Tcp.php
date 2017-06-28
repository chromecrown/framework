<?php

namespace Wpt\Framework\Client\Pool;

use Wpt\Framework\Pool\Pool;
use Wpt\Framework\Core\Packet;
use Wpt\Framework\Coroutine\CoroutineInterface;
use Wpt\Framework\Core\Application;
use Wpt\Framework\Client\Tcp as TcpClient;
use Wpt\Framework\Support\AsyncTcpTrait;

/**
 * Class TcpPool
 *
 * @package Wpt\Framework\Client\Pool
 */
class Tcp extends Pool implements CoroutineInterface
{
    use AsyncTcpTrait;

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
        $this->type   = 'tcp';
        $this->name   = $name;
        $this->app    = $app;
        $this->packet = $packet;

        if (! isset($config['config'])) {
            throw new \Exception('Tcp connect config not found.');
        }

        $this->setConfig($config['config']);
        $this->setSet($config['set'] ?? []);
        unset($config);

        parent::__construct();
    }

    /**
     * @param callable $callback
     */
    public function send(callable $callback)
    {
        $request = $this->format
            ? $this->packet->encode($this->packet->format($this->data), $this->set['package_eof'])
            : $this->data;

        $data = [
            'request' => $request,
            'token'   => $this->getToken($callback, true),
            'retry'   => 0,
            'format'  => $this->format,
        ];

        $this->data = $this->format = null;

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
            $isEnd = true;
            if ($data['format']) {
                list($result, $isEnd) = $this->parseResult($result);
            }

            if ($isEnd) {
                $this->release($client);
            }

            $this->callback($data['token'], $result, $isEnd);
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
}
