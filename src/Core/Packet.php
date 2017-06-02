<?php

namespace Flower\Core;

use Flower\Log\Log;
use Flower\Core\Application;

/**
 * Class Packet
 * @package Flower\Packet
 */
class Packet
{
    /**
     * @var string
     */
    private $packageEof = "#\r\n\r\n";

    /**
     * @var string
     */
    private $splitEof   = "#\r#\n#";

    /**
     * Packet constructor.
     * @param Config $config
     * @param string $handler
     * @param string $packageEof
     * @param string $splitEof
     */
    public function __construct(Config $config, $handler = null, $packageEof = null, $splitEof = null)
    {
        $serverConfig = $config->get('server_config', []);

        $this->setSplitEof($packageEof ?: ($serverConfig['split_eof'] ?? '#\r#\n#'));
        $this->setPackageEof($splitEof ?: ($serverConfig['package_eof'] ?? '#\r\n\r\n'));
        unset($serverConfig);
    }

    /**
     * @param string $eof
     */
    public function setPackageEof($eof = "#\r\n\r\n")
    {
        $this->packageEof = $eof;
    }

    /**
     * @return string
     */
    public function getPackageEof()
    {
        return $this->packageEof;
    }

    /**
     * @param string $eof
     */
    public function setSplitEof($eof = "#\r#\n#")
    {
        $this->splitEof = $eof;
    }

    /**
     * @return string
     */
    public function getSplitEof()
    {
        return $this->splitEof;
    }

    /**
     * @param $data
     * @return string
     */
    public function pack($data)
    {
        return msgpack_pack($data);
    }

    /**
     * @param $data
     * @return mixed
     */
    public function unpack($data)
    {
        return msgpack_unpack($data);
    }

    /**
     * @param array  $data
     * @param int    $code
     * @return array
     */
    public function format($data = [], $code = 200)
    {
        return [
            "code" => $code,
            "data" => $data,
        ];
    }

    /**
     * @param $data
     * @param null $eof
     * @return string
     */
    public function encode($data, $eof = null)
    {
        return $this->pack($data). ($eof ?: $this->packageEof);
    }

    /**
     * @param $str
     * @param null $eof
     * @return array
     */
    public function decode($str, $eof = null)
    {
        $eof = $eof ?: $this->packageEof;

        if (strpos($str, $eof) !== false) {
            $str = str_replace($eof, '', $str);
        }

        try {
            $str = $this->unpack($str);
        }
        catch (\Exception $e) {
            Log::error('decode message error: '. $e->getMessage());

            $str = [];
        }

        return $str;
    }
}
