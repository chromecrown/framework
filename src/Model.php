<?php

namespace Weipaitang\Framework;

use Weipaitang\Config\ConfigInterface;
use Weipaitang\Database\Model as AbstractModel;
use Weipaitang\Server\Server;

/**
 * Class Model
 * @package Weipaitang\Framework
 */
class Model extends AbstractModel
{
    /**
     * @param ConfigInterface $config
     * @param Server          $server
     */
    public function __constract(ConfigInterface $config, Server $server)
    {
        if ($this->enableQueryCache === null) {
            $this->enableQueryCache = $config->get('enable_query_cache', false);
        }

        if ($this->cacheTime === null) {
            $this->cacheTime = $config->get('query_cache_time', 86400);
        }

        if (! $this->master) {
            $this->master = $config->get('mysql_default');
        }

        $this->withEnableAsync(! ($server->getServer()->taskworker ?? false));
    }

    /**
     * @param $name
     * @param $arguments
     * @return Model
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return app()->make(get_called_class())->$name(...$arguments);
    }
}
