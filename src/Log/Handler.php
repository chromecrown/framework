<?php

namespace Flower\Log;

use Flower\Core\Application;

/**
 * Class Handler
 *
 * @package Flower\Log
 */
abstract class Handler
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * Handler constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    abstract function write(array $data);
}