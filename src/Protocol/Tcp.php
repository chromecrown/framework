<?php

namespace Weipaitan\Framework\Protocol;

use Weipaitang\Console\Output;
use Weipaitang\Packet\Packet;
use Weipaitang\Server\Server;
use Weipaitang\Framework\RunInfo;
use Weipaitang\Framework\Controller;
use Weipaitang\ServiceCenter\Manage;
use Swoole\Server as SwooleServer;

/**
 * Class Tcp
 * @package Weipaitan\Framework\Protocol
 */
class Tcp extends Protocol
{
    /**
     * @var string
     */
    protected $type = 'Tcp';

    /**
     * @return void
     */
    public function register()
    {
        $this->server->withHook(Server::ON_CONNECT, [$this, 'onConnect']);
        $this->server->withHook(Server::ON_RECEIVE, [$this, 'onReceive']);
        $this->server->withHook(Server::ON_CLOSE,   [$this, 'onClose']);
    }

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
                    (new Manage)->withServer($server)
                        ->withFd($fd)
                        ->dispatch($data);
                    break;

                case 'status' :
                    $this->status($server);
                    break;

                default :
                    throw new \Exception('Request type not supported.');
            }

            unset($data);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $request = json_encode($data, JSON_UNESCAPED_UNICODE);

//            Log::error("{$this->type} dispatcher : " . $message, $data);
//            Output::debug("{$this->type} dispatcher exception: {$message}, {$request}", 'red');

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
     *
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
            $this->app->get('co.scheduler')->newTask($generator)->run();
        }
    }

    /**
     * @param SwooleServer $server
     *
     * @return array
     */
    protected function status(SwooleServer $server)
    {
        $status = $server->stats();
        unset($status['worker_request_count']);

        $status['load_avg'] = sys_getloadavg();

        /**
         * @var RunInfo $runInfo
         */
        $runInfo = $this->app->get('runinfo');

        $total = $runInfo->get('total');
        $status['total'] = [
            'success'  => $total ? $total['success'] : 0,
            'failure'  => $total ? $total['failure'] : 0,
            'avg_time' => ($total['success'] or $total['failure'])
                ? bcdiv($total['time'], ($total['success'] + $total['failure']), 7)
                : 0,
        ];
        unset($total);

        $time = time();
        $startTime = $time - $status['start_time'];

        $total  = $status['total']['success'] + $status['total']['failure'];

        $qpsSec = $runInfo->get('qps_' . ($time - 1))['success'] ?? 0;
        $qpsAvg = $startTime ? ceil($total / $startTime) : $total;
        $qpsMax = $runInfo->get('qps_max')['success'] ?? 0;

        $status['qps'] = "{$qpsSec}, {$qpsAvg}, {$qpsMax}";

        return $status;
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
