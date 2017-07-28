<?php

namespace Weipaitan\Framework\Protocol;

use Weipaitang\Packet\JsonHandler;
use Weipaitang\Packet\Packet;
use Weipaitang\Server\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server as SwooleWebSocketServer;
use Swoole\WebSocket\Frame as SwooleWebSocketFrame;

/**
 * Class WebSocket
 * @package Weipaitan\Framework\Protocol
 */
class WebSocket extends Tcp
{
    /**
     * @var array
     */
    protected $register = [
        Server::ON_OPEN    => 'onOpen',
        Server::ON_MESSAGE => 'onMessage',
        Server::ON_CLOSE   => 'onClose',
    ];

    /**
     * @var string
     */
    protected $type = 'WebSocket';

    /**
     * @param SwooleWebSocketServer $server
     * @param SwooleWebSocketFrame  $frame
     */
    public function onMessage(SwooleWebSocketServer $server, SwooleWebSocketFrame $frame)
    {
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
        $server->push($frame->fd, "this is server");

        /**
         * @var Packet $packet
         */
        $packet = $this->app->get('packet');

        try {
            $data = (new JsonHandler())->unpack($frame->data);

            if (! isset($data['request']) or ! $data['request']) {
                throw new \Exception('Unknown request.');
            }

            $data['method']  = $data['method'] ?? '';
            $data['args']    = $data['args'] ?? [];

            $this->dispatchApi(
                $server,
                $data,
                $frame->fd
            );
        } catch (\Exception $e) {
            $message = $e->getMessage();

//            Log::error('{$this->type} dispatcher : ' . $message, $data);
//            Output::debug("{$this->type} dispatcher exception: {$message}", 'red');

            $server->push(
                $frame->fd,
                (new JsonHandler())->pack(
                    $packet->format($message, 500)
                )
            );
        }
    }

    /**
     * @param SwooleWebSocketServer $server
     * @param Request               $request
     */
    public function onOpen(SwooleWebSocketServer $server, Request $request)
    {

    }
}
