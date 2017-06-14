<?php

namespace Flower\Log\Handler;

use Flower\Contract\LogHandler;
use Flower\Support\Construct;

/**
 * Class Redis
 *
 * @package Flower\Log\Handler
 */
class Redis implements LogHandler
{
    use Construct;

    public function write(array $data)
    {
        try {
            $this->app->get('redis', 'log')->call(
                null,
                'rPush',
                [$data['service'], $this->app['packet']->pack($data)],
                false,
                false
            );
        }
        catch (\Exception $e) {

        }

        unset($data);
    }
}
