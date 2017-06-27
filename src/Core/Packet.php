<?php

namespace Wpt\Framework\Core;

use Wpt\Framework\Log\Log;
use Wpt\Framework\Core\Application;

/**
 * Class Packet
 *
 * @package Wpt\Framework\Packet
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
    private $splitEof = "#\r#\n#";

    /**
     * Packet constructor.
     *
     * @param Config $config
     * @param string $packageEof
     * @param string $splitEof
     */
    public function __construct(Config $config, string $packageEof = null, string $splitEof = null)
    {
        $serverConfig = $config->get('server_config', []);

        $this->setSplitEof($packageEof ?: ($serverConfig['split_eof'] ?? "#\r#\n#"));
        $this->setPackageEof($splitEof ?: ($serverConfig['package_eof'] ?? "#\r\n\r\n"));
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
     * @param mixed $data
     * @return string
     */
    public function pack($data)
    {
        return msgpack_pack($data);
    }

    /**
     * @param string $data
     * @return mixed
     */
    public function unpack(string $data)
    {
        return msgpack_unpack($data);
    }

    /**
     * @param mixed $data
     * @param int   $code
     * @return array
     */
    public function format($data = null, int $code = 200)
    {
        return [
            "code" => $code,
            "data" => $data,
        ];
    }

    /**
     * @param mixed  $data
     * @param string $eof
     * @return string
     */
    public function encode($data, string $eof = null)
    {
        return $this->pack($data) . ($eof ?: $this->packageEof);
    }

    /**
     * @param string $str
     * @param string $eof
     * @return array
     */
    public function decode(string $str, string $eof = null)
    {
        $eof = $eof ?: $this->packageEof;

        if (strpos($str, $eof) !== false) {
            $str = str_replace($eof, '', $str);
        }

        try {
            $str = $this->unpack($str);
        } catch (\Exception $e) {
            Log::error('decode message error: ' . $e->getMessage());

            $str = [];
        }

        return $str;
    }
}
