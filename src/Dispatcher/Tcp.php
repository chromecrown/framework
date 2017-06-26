<?php

namespace Flower\Dispatcher;

use Flower\Core\Controller;
use Flower\Log\Log;
use Flower\Utility\Console;
use Swoole\Server as SwooleServer;

/**
 * Class Tcp
 *
 * @package Flower\Dispatcher
 */
class Tcp extends Base
{
    /**
     * @param SwooleServer $server
     * @param int          $fd
     * @param int          $fromId
     * @param string       $data
     */
    public function dispatch(SwooleServer $server, int $fd, int $fromId, string $data)
    {
        try {
            // 解包消息
            $data = $this->app['packet']->decode($data);

            if ($data['code'] !== 200) {
                throw new \Exception('Unknown message.', $data['code']);
            }

            $data = $data['data'];

            // 判断是请求 API 还是投递 Task
            switch ($data['type']) {
                case 'api' :
                    $this->api($data, $fd);
                    break;

                case 'manage' :
                    $this->app->get('manage.service')->run($data, $fd);
                    break;

                case 'status' :
                    $this->server->send($fd, $this->status());
                    break;

                default :
                    throw new \Exception('request type error.');
            }

            unset($data);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $request = json_encode($data, JSON_UNESCAPED_UNICODE);

            Log::error('Dispatcher : ' . $message, $data);

            if (DEBUG_MODEL) {
                Console::debug("Tcp dispatcher exception: {$message}, {$request}", 'red');
            }

            // 挂了，返回错误信息
            $this->server->send($fd, $message, 500);
        }
    }

    /**
     * @param  array $data
     * @param  int   $fd
     * @throws \Exception
     */
    public function api(array $data, int $fd = null)
    {
        $request = $this->parseRequest($data['request'] ?: null);
        $method = $data['method'] ?? 'index';

        $object = $this->app->make($request);

        // 请求的对象木有找到
        if (! method_exists($object, $method)) {
            throw new \Exception('Tcp Request Not Found:' . $request . ':' . $method);
        }

        /**
         * @var Controller $object
         */
        $object->withFd($fd);

        if (DEBUG_MODEL) {
            Console::debug(' TCP ' . $this->getRequestString($request, $method, $data['args']), 'blue');
        }

        $generator = $object->$method(...array_values($data['args'] ?: []));
        unset($data);

        if ($generator instanceof \Generator) {
            $this->app->get('co.scheduler')->newTask($generator)->run();
        }
    }

    /**
     * @param  string $request
     * @return string
     * @throws \Exception
     */
    protected function parseRequest(string $request)
    {
        if (! $request) {
            throw new \Exception('Tcp Request Not Found');
        }

        $request = ucfirst($request);
        if (strpos($request, '/') !== false) {
            $request = join('\\', array_map('ucfirst', explode('/', $request)));
        }

        $namespace = '\App\Tcp\\';

        if (class_exists($namespace . $request)) {
            return $namespace . $request;
        }

        throw new \Exception('Tcp Request Not Found:' . $request);
    }

    /**
     * @return array
     */
    protected function status()
    {
        $status = $this->server->getServer()->stats();
        unset($status['worker_request_count']);

        $status['load_avg'] = sys_getloadavg();

        $total = $this->app->getRunTable()->get('total');
        $status['total'] = [
            'success'  => $total ? $total['success'] : 0,
            'failure'  => $total ? $total['failure'] : 0,
            'avg_time' => ($total['success'] or $total['failure']) ? bcdiv($total['time'],
                ($total['success'] + $total['failure']), 7) : 0,
        ];
        unset($total);

        $time = time();
        $startTime = $time - $status['start_time'];

        $total = $status['total']['success'] + $status['total']['failure'];

        $qpsSec = $this->app->getRunTable()->get('qps_' . ($time - 1))['success'] ?? 0;
        $qpsAvg = $startTime ? ceil($total / $startTime) : $total;
        $qpsMax = $this->app->getRunTable()->get('qps_max')['success'] ?? 0;

        $status['qps'] = "{$qpsSec}, {$qpsAvg}, {$qpsMax}";

        return $status;
    }
}
