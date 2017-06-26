<?php

namespace Flower\Client\Async;

use Flower\Utility\File as AsyncFile;

/**
 * Class File
 * @package Flower\Client\Async
 */
class File extends Base
{
    private $file;
    private $data;
    private $flag;
    private $method;

    /**
     * @param string $file
     * @return \Generator
     */
    public function read(string $file)
    {
        $this->method = 'read';
        $this->file   = $file;

        return yield $this;
    }

    /**
     * @param string $file
     * @param mixed  $data
     * @param int    $flag
     * @return \Generator
     */
    public function write(string $file, $data, int $flag = 0)
    {
        $this->method = 'write';
        $this->file   = $file;
        $this->data   = $data;
        $this->flag   = $flag;

        return yield $this;
    }

    /**
     * @param callable $callback
     */
    public function send(callable $callback)
    {
        $this->callback = $callback;

        $this->startTick();

        if ($this->method === 'read') {
            AsyncFile::read($this->file, function ($filename, $result) {
                $this->clearTick();

                ($this->callback)($result);
            });
        } else {
            AsyncFile::write($this->file, $this->data, $this->flag, function ($filename) {
                $this->clearTick();

                ($this->callback)($filename);
            });
        }
    }
}

