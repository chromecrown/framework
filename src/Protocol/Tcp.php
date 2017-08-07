<?php

namespace Weipaitang\Framework\Protocol;

use Weipaitang\Console\Output;
use Weipaitang\Framework\ServiceCenter\Manage;
use Weipaitang\Log\Log;
use Weipaitang\Packet\Packet;
use Weipaitang\Server\Server;
use Weipaitang\Framework\Controller;
use Swoole\Server as SwooleServer;

/**
 * Class Tcp
 * @package Weipaitang\Framework\Protocol
 */
class Tcp extends Protocol
{
    /**
     * @var array
     */
    protected $register = [
        Server::ON_CONNECT => 'onConnect',
        Server::ON_RECEIVE => 'onReceive',
        Server::ON_CLOSE   => 'onClose',
    ];

    /**
     * @var string
     */
    protected $type = 'Tcp';

    /**
     * @param SwooleServer $server
     * @param int          $fd
     * @param int          $fromId
     * @param string       $data
     */
    public function onReceive(SwooleServer $server, int $fd, int $fromId, string $data)
    {
        $this->dispatch($server, $fd, $fromId, $data);
    }

    /**
     * @param SwooleServer $server
     * @param int          $fd
     * @param int          $fromId
     * @param string       $data
     */
    public function dispatch(SwooleServer $server, int $fd, int $fromId, string $data)
    {
        try {
            /**
             * @var Packet $packet
             */
            $packet = $this->app->get('packet');

            $data = $packet->decode($data);

            if (! is_array($data)
                or ! isset($data['code'])
                or ! isset($data['data'])
                or $data['code'] !== 200
            ) {
                throw new \Exception('Unrecognized message format.');
            }

            $data = $data['data'];

            switch ($data['type']) {
                case 'api' :
                    if (! isset($data['request']) or ! $data['request']) {
                        throw new \Exception('Unknown request.');
                    }

                    $data['method']  = $data['method'] ?? '';
                    $data['args']    = $data['args'] ?? [];

                    $this->dispatchApi($server, $data, $fd, $fromId);
                    break;

                case 'manage' :
                    $this->app->get('manage')
                        ->withServer($server)
                        ->withFd($fd)
                        ->dispatch($data);
                    break;

                default :
                    throw new \Exception('Request type not supported.');
            }

            unset($data);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $request = json_encode($data, JSON_UNESCAPED_UNICODE);

            Log::error("{$this->type} dispatcher : " . $message, $data);
            Output::debug("{$this->type} dispatcher exception: {$message}, {$request}", 'red');

            $server->send(
                $fd,
                $packet->encode(
                    $packet->format($message, 500)
                ),
                $fromId
            );
        }
    }

    /**
     * @param SwooleServer $server
     * @param array        $data
     * @param int|null     $fd
     * @param int|null     $fromId
     * @throws \Exception
     */
    public function dispatchApi(SwooleServer $server, array $data, int $fd = null, int $fromId = null)
    {
        $request = ucfirst($data['request']);
        if (strpos($request, '/') !== false) {
            $request = join('\\', array_map('ucfirst', explode('/', $request)));
        }

        $namespace = '\App\\'. $this->type. '\\';

        if (! class_exists($namespace . $request)) {
            throw new \Exception("Request not found. [{$request}]");
        }

        $request = $namespace. $request;
        $method = $data['method'] ?? 'index';

        $object = $this->app->make($request);

        if (! method_exists($object, $method)) {
            throw new \Exception("Request not found. [{$request}:{$method}]");
        }

        /**
         * @var Controller $object
         */
        $object->withServer($server);
        $object->withFd($fd);
        $object->withFromId($fromId);

        $this->logRequest($this->type, $request, $method, $data['args']);

        $generator = $object->$method(...array_values($data['args'] ?: []));
        unset($data);

        if ($generator instanceof \Generator) {
            $this->app->get('coroutine')->newTask($generator)->run();
        }
    }

    /**
     * @param SwooleServer $server
     * @param int          $fd
     * @param int          $fromId
     */
    public function onConnect(SwooleServer $server, int $fd, int $fromId)
    {

    }

    /**
     * @param SwooleServer $server
     * @param int          $fd
     * @param int          $fromId
     */
    public function onClose(SwooleServer $server, int $fd, int $fromId)
    {

    }
}
