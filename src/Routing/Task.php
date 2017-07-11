<?php

namespace Weipaitang\Framework\Dispatcher;

use Weipaitang\Framework\Utility\Console;

/**
 * Class Task
 *
 * @package Weipaitang\Framework\Dispatcher
 */
class Task extends Base
{
    /**
     * @param array $data
     */
    public function dispatch(array $data)
    {
        $data['param'] = $data['param'] ?? [];

        $this->app->get('co.scheduler')->newTask(
            $this->dispatchRun($data)
        )->run();
    }

    /**
     * @param array $data
     *
     * @return \Generator|void
     * @throws \Exception
     */
    private function dispatchRun(array $data)
    {
        $request = $this->parseRequest($data['request'] ?: null);
        $method = $data['method'] ?? 'index';

        $object = $this->app->make($request);

        // 请求的对象木有找到
        if (! method_exists($object, $method)) {
            throw new \Exception('Task Not Found: ' . $request . ':' . $method . ')');
        }

        $queryString = $this->getRequestString($request, $method, $data['param']);

        if (DEBUG_MODEL) {
            Console::debug('TASK ' . $queryString, 'blue');
        }

        $lockKey = '';
        $lockInstance = null;
        $needLock = isset($data['lock']) and $data['lock'];
        if ($needLock) {
            $lockInstance = $this->app->get('lock');

            $lockKey = md5($request . $method . $this->app['packet']->pack($data['param']));
            if (yield $lockInstance->lock($lockKey)) {
                if (DEBUG_MODEL) {
                    Console::debug("TASK {$queryString} (already locked)", 'blue');
                }

                return;
            }
        }

        try {
            $generator = $object->$method(...array_values($data['param']));
            unset($data);

            if ($generator instanceof \Generator) {
                $this->app->get('co.scheduler')->newTask($generator)->run();
            }
        } catch (\Exception $e) {
            $needLock && yield $lockInstance->unlock($lockKey);

            throw new \Exception($e->getMessage());
        }

        $needLock && yield $lockInstance->unlock($lockKey);
        unset($lockInstance);
    }

    /**
     * @param $request
     * @return string
     * @throws \Exception
     */
    protected function parseRequest($request)
    {
        if (! $request) {
            throw new \Exception('Task Not Found');
        }

        $request = ucfirst($request);
        if (strpos($request, '/') !== false) {
            $request = join('\\', array_map('ucfirst', explode('/', $request)));
        }

        $namespace = '\App\Task\\';
        if (class_exists($namespace . $request)) {
            return $namespace . $request;
        }

        throw new \Exception('Task Not Found:' . $request);
    }
}
