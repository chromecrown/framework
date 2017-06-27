<?php

namespace Wpt\Framework\Pool;

/**
 * Class Manager
 *
 * @package Wpt\Framework\Pool
 */
class Manager
{
    /**
     * @var array
     */
    protected $register = [];

    /**
     * @var array
     */
    protected $alias = [];

    /**
     * @param PoolInterface $pool
     * @param array         $alias
     */
    public function register(PoolInterface $pool, array $alias = [])
    {
        $type = $pool->getType();
        $name = $pool->getName();

        $this->register[$type][$name] = $pool;

        if ($alias) {
            $this->alias[$type][$name] = $alias;

            foreach ($alias as $v) {
                $this->register[$type][$v] = $pool;
            }
        }
    }

    /**
     * @param string $type
     * @param string $name
     * @return null
     */
    public function get(string $type, string $name)
    {
        return $this->register[$type][$name] ?? null;
    }

    /**
     * @param string $type
     * @param string $name
     * @return bool
     */
    public function exists(string $type, string $name)
    {
        return isset($this->register[$type][$name]);
    }

    /**
     * @param string $type
     * @param string $name
     */
    public function remove(string $type, string $name)
    {
        unset($this->register[$type][$name]);

        if (isset($this->alias[$type][$name])) {
            unset($this->alias[$type][$name]);
        }
    }
}
