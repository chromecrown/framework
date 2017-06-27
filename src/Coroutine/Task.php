<?php

namespace Wpt\Framework\Coroutine;

/**
 * Class Task
 *
 * @package Wpt\Framework\Coroutine
 */
class Task
{
    /**
     * @var mixed
     */
    private $data = null;

    /**
     * @var \SplStack
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

                if ($value instanceof \Generator) {
                    $this->stack->push($generator);
                    $generator = $value;

                    continue;
                }

                // async
                if ($value instanceof CoroutineInterface) {
                    $this->stack->push($generator);
                    $value->send(function ($data) {
                        $this->data = $data;

                        $generator = $this->stack->pop();
                        $generator->send($data);

                        $this->run($generator);
                    });

                    return;
                }

                if (null !== $value) {
                    $this->data = $value;
                    $generator->send($this->data);
                    continue;
                }

                try {
                    $this->data = $generator->getReturn();
                } catch (\Exception $e) {
                    $this->data = null;
                }

                if ($this->stack->isEmpty()) {
                    if ($this->coroutine->valid()) {
                        $this->coroutine->next();
                        continue;
                    }

                    return;
                }

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
