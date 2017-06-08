<?php

namespace Flower\Coroutine;

use Flower\Contract\Coroutine as ICoroutine;

/**
 * Class Task
 *
 * @package Flower\Coroutine
 */
class Task
{
    /**
     * @var null
     */
    private $data = null;

    /**
     * @var null|\SplStack
     */
    private $stack = null;

    /**
     * @var \Generator
     */
    private $coroutine = null;

    /**
     * Task constructor.
     */
    public function __construct()
    {
        $this->stack = new \SplStack();
    }

    /**
     * @param \Generator $routine
     * @return $this
     */
    public function setCoroutine(\Generator $routine)
    {
        $this->coroutine = $routine;

        return $this;
    }

    /**
     * @return \Generator
     */
    public function getCoroutine()
    {
        return $this->coroutine;
    }

    /**
     * @param \Generator $generator
     */
    public function run(\Generator $generator)
    {
        while (true) {
            try {
                if (! $generator) {
                    return;
                }

                $value = $generator->current();

                // 是迭代器就入栈
                if ($value instanceof \Generator) {
                    $this->stack->push($generator);
                    $generator = $value;

                    continue;
                }

                // 异步操作
                if ($value instanceof ICoroutine) {
                    $this->stack->push($generator);
                    $value->send(function ($data) {
                        if (is_array($data) and isset($data['exception'])) {
                            throw new \Exception($data['exception']);
                        } else {
                            $this->data = $data;

                            $generator = $this->stack->pop();
                            $generator->send($data);

                            // 返回了就继续
                            $this->run($generator);
                        }
                    });

                    return;
                }

                if (null !== $value) {
                    $this->data = $value;
                    $generator->send($this->data);
                    continue;
                }

                $this->data = $generator->getReturn();

                if ($this->stack->isEmpty()) {
                    if ($this->coroutine->valid()) {
                        $this->coroutine->next();
                        continue;
                    }

                    return;
                }

                // 栈没空就继续返回
                $generator = $this->stack->pop();
                $generator->send($this->data);

                $this->data = null;
            } catch (\Exception $e) {
                while (! $this->stack->isEmpty()) {
                    $this->coroutine = $this->stack->pop();
                }

                $generator->throw($e);

                return;
            }
        }
    }
}
