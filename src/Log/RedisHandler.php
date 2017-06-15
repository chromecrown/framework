<?php

namespace Flower\Log;

use Flower\Support\Construct;

/**
 * Class RedisHandler
 *
 * @package Flower\Log
 */
class RedisHandler implements LogHandlerInterface
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
