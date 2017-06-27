<?php

namespace Wpt\Framework\Log;

use Wpt\Framework\Support\Construct;

/**
 * Class RedisHandler
 *
 * @package Wpt\Framework\Log
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
