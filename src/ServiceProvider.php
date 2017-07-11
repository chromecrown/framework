<?php

namespace Weipaitang\Framework;

/**
 * Class ServiceProvider
 *
 * @package Wpt\Framework\Support
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

    abstract function handler();
}
