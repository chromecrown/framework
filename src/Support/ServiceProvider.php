<?php

namespace Flower\Support;

use Flower\Core\Application;

/**
 * Class ServiceProvider
 *
 * @package Flower\Support
 */
abstract class ServiceProvider
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * ServiceProvider constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }
}
