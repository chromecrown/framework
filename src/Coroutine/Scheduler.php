<?php

namespace Flower\Coroutine;

use Flower\Core\Application;

/**
 * Class Scheduler
 * @package Flower\Coroutine
 */
class Scheduler
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @var \SplQueue
     */
    private $taskQueue;

    /**
     * Scheduler constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->taskQueue = new \SplQueue();
    }

    /**
     * @param \Generator $routine
     * @return $this
     */
    public function newTask(\Generator $routine)
    {
        $this->scheduler(
            $this->app->get('co.task')->setCoroutine($routine)
        );

        return $this;
    }

    /**
     * @param Task $task
     */
    public function scheduler(Task $task)
    {
        $this->taskQueue->enqueue($task);
    }

    public function run()
    {
        if ($this->taskQueue->isEmpty()) {
            return;
        }

        while ( ! $this->taskQueue->isEmpty()) {
            $task = $this->taskQueue->dequeue();
            $task->run($task->getCoroutine());
        }
    }
}
