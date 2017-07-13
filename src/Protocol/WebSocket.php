<?php

namespace Weipaitan\Framework\Protocol;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Weipaitang\Packet\JsonHandler;
use Weipaitang\Packet\Packet;
use Weipaitang\Server\Server;
use Swoole\WebSocket\Server as SwooleWebSocketServer;
use Swoole\WebSocket\Frame as SwooleWebSocketFrame;

/**
 * Class WebSocket
 * @package Weipaitan\Framework\Protocol
 */
class WebSocket extends Tcp
{
    /**
     * @var string
     */
    protected $type = 'WebSocket';

    /**
     * @return void
     */
    public function register()
    {
        $this->server->hook(Server::ON_HAND_SHAKE, [$this, 'onHandShake']);
        $this->server->hook(Server::ON_OPEN,       [$this, 'onOpen']);
        $this->server->hook(Server::ON_MESSAGE,    [$this, 'onMessage']);
        $this->server->hook(Server::ON_CLOSE,      [$this, 'onClose']);
    }

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
     * @param Request  $request
     * @param Response $response
     */
    public function onHandShake(Request $request, Response $response)
    {

    }

    /**
     * @param SwooleWebSocketServer $server
     * @param Request               $request
     */
    public function onOpen(SwooleWebSocketServer $server, Request $request)
    {

    }
}
