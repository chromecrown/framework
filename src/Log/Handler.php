<?php

namespace Flower\Log;

use Flower\Core\Application;

abstract class Handler
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    abstract function write(array $data);
}