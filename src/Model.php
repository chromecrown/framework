<?php

namespace Weipaitang\Framework;

use Weipaitang\Config\ConfigInterface;
use Weipaitang\Database\Model as AbstractModel;

/**
 * Class Model
 * @package Weipaitang\Framework
 */
class Model extends AbstractModel
{
    /**
     * @param ConfigInterface $config
     */
    public function __constract(ConfigInterface $config)
    {
        if ($this->enableQueryCache === null) {
            $this->enableQueryCache = $config->get('enable_query_cache', false);
        }

        if ($this->cacheTime === null) {
            $this->cacheTime = $config->get('query_cache_time', 86400);
        }
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
