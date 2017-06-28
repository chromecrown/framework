<?php

namespace Wpt\Framework\Client\Pool;

use Wpt\Framework\Log\Log;
use Wpt\Framework\Pool\Pool;
use Wpt\Framework\Core\Packet;
use Wpt\Framework\Coroutine\CoroutineInterface;
use Wpt\Framework\Core\Application;
use Wpt\Framework\Client\Tcp as TcpClient;

/**
 * Class TcpPool
 *
 * @package Wpt\Framework\Client\Pool
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
    private $data;

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
     * @var string
     */
    private $splitEof = "#\r#\n#";

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
     * @param array $set
     *
     * @return $this
     */
    public function setSet(array $set = [])
    {
        if ($set) {
            $this->set = array_merge($this->set, $set);
        }

        return $this;
    }

    /**
     * @param string $eof
     *
     * @return $this
     */
    public function setSplitEof(string $eof = '')
    {
        if ($eof) {
            $this->splitEof = $eof;
        }

        return $this;
    }

    /**
     * @param callable $callback
     * @param mixed    $data
     * @param bool     $format
     */
    public function call(callable $callback, $data, bool $format = true)
    {
        $this->data = $data;
        $this->format  = $format;

        $this->send($callback);
    }

    /**
     * @param mixed $data
     * @param bool  $format
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
     * @param $data
     *
     * @return array
     */
    private function parseResult($data)
    {
        if (false === strpos($data, $this->splitEof)) {
            return [
                $this->packet->decode($data, $this->set['package_eof']),
                true
            ];
        }

        $data = $this->packet->decode(
            str_replace($this->splitEof, '', $data),
            $this->set['package_eof']
        )['data'];

        $isEnd = isset($data['_is_end_']);
        unset($data['_is_end_']);

        return [
            [
                'code'     => 200,
                'data'     => $data,
                'is_batch' => true,
                'is_end'   => $isEnd
            ],
            $isEnd
        ];
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
            if ($client->errCode > 0) {
                Log::error('Tcp Connection closed, code: '. $client->errCode);
            }
        }
    }
}
