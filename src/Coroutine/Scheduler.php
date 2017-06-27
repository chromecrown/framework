<?php

namespace Wpt\Framework\Coroutine;

use Wpt\Framework\Core\Application;

/**
 * Class Scheduler
 *
 * @package Wpt\Framework\Coroutine
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
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->taskQueue = new \SplQueue();
    }

    /**
     * @param \Closure|\Generator $routine
     * @return $this
     */
    public function newTask($routine)
    {
        $this->scheduler(
            $this->app->get('co.task')->setCoroutine($routine instanceof \Closure ? $routine() : $routine)
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

    /**
     * run
     */
    public function run()
    {
        if ($this->taskQueue->isEmpty()) {
            return;
        }

        while (! $this->taskQueue->isEmpty()) {
            /**
             * @var Task $task
             */
            $task = $this->taskQueue->dequeue();

            $task->run($task->getCoroutine());
        }
    }
}
