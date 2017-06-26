<?php

namespace Flower\Client\Sync;

use Flower\Core\Packet;
use Swoole\Client as SwooleClient;

/**
 * Class Tcp
 *
 * @package Flower\Client\Sync
 */
class Tcp
{
    /**
     * @var Packet
     */
    private $packet;

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

    private $config = [];

    /**
     * Tcp constructor.
     *
     * @param Packet $packet
     * @param array  $config
     */
    public function __construct(Packet $packet, array $config = [])
    {
        $this->packet = $packet;

        $this->config = $config['config'];
        $this->set = array_merge($this->set, $config['set'] ?? []);
        unset($config);
    }

    /**
     * @param array $data
     * @param bool  $format
     * @return array|null|string
     */
    public function request(array $data, bool $format = true)
    {
        $data = $format
            ? $this->packet->encode($this->packet->format($data), $this->set['package_eof'])
            : $data;

        $client = new SwooleClient(SWOOLE_SOCK_TCP);
        $client->set($this->set);

        $result = null;
        try {
            if (! $client->connect($this->config['host'], $this->config['port'], $this->config['timeout'] ?? 3)) {
                throw new \Exception('connect failure.');
            }

            $client->send($data);

            $result = $client->recv();
        } catch (\Exception $e) {

        }

        return ($result and $format) ? $this->packet->decode($result, $this->set['package_eof']) : $result;
    }
}
