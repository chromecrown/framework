<?php

namespace Wpt\Framework\Support;

use Wpt\Framework\Log\Log;
use Wpt\Framework\Core\Packet;
use Wpt\Framework\Core\Application;
use Wpt\Framework\Client\Tcp as TcpClient;

trait AsyncTcpTrait
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Packet
     */
    protected $packet;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @var boolean
     */
    protected $format;

    /**
     * @var string
     */
    protected $splitEof = "#\r#\n#";

    /**
     * @var array
     */
    protected $set = [
        'open_eof_check' => 1,
        'open_eof_split' => 1,
        'package_eof'    => "#\r\n\r\n",

        'package_max_length' => 1024 * 1024 * 2,
        'open_tcp_nodelay'   => 1,
    ];

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
     * @param $data
     *
     * @return array
     */
    protected function parseResult($data)
    {
        $result = strpos($data, $this->splitEof);
        if (false === $result) {
            return [
                $this->packet->decode($data, $this->set['package_eof']),
                true
            ];
        }

        if ($result > 0) {
            $tmp  = [];
            $data = explode($this->splitEof, $result);
            foreach ($data as &$item) {
                $item = $this->packet->decode($item, $this->set['package_eof']);

                if (! empty($item['data']) and is_array($item['data'])) {
                    $tmp = array_merge($tmp, $item['data']);
                }
            }
            unset($data);

            return [
                [
                    'code' => 200,
                    'data' => $tmp,
                ],
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
