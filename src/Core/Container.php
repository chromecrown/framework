<?php

namespace Flower\Core;

/**
 * Class Container
 * @package Flower\Core
 */
class Container implements \ArrayAccess
{
    /**
     * 唯一实例
     *
     * @var Container
     */
    protected static $instance;

    /**
     * 别名列表
     *
     * @var array
     */
    protected $alias = [];

    /**
     * 注册的服务
     *
     * @var array
     */
    protected $register  = [];

    /**
     * 已经实例化的服务
     *
     * @var array
     */
    protected $shared = [];

    /**
     * 获取服务
     *
     * @param  string $name
     * @param  array $arguments
     * @return object|null
     */
    public function get($name, ...$arguments)
    {
        if (isset($this->shared[$name])) {
            return $this->shared[$name];
        }

        if (! isset($this->register[$name])) {
            throw new \InvalidArgumentException('services not found: '. $name);
        }

        $instance = $this->make($this->register[$name]['class'], $arguments);
        unset($arguments);

        if ($instance and $this->register[$name]['shared'] == true) {
            $this->shared[$name] = $instance;
        }

        return $instance;
    }

    /**
     * 实例化一个资源
     *
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws \Exception
     */
    public function make($name, $arguments = [])
    {
        // 是否匿名函数
        $isClosure = ($name instanceof \Closure) ? true : false;

        // 通过反射探测参数
        $args = $this->detection($isClosure, $name);
        if ($args) {
            $instance = [];
            if ($arguments) {
                foreach ($arguments as $k => $v) {
                    if (is_object($v)) {
                        $instance[$k] = $v;
                    }
                }
            }

            $args = array_reverse($args);
            foreach ($args as $v) {
                if ($instance) {
                    $find = false;
                    foreach ($instance as $k => $ins) {
                        if ($ins instanceof $v) {
                            array_unshift($arguments, $ins);
                            unset($arguments[$k]);

                            $find = true;
                            break;
                        }
                    }

                    if ($find) {
                        continue;
                    }
                }

                $alias = $this->alias[$v] ?? null;
                if (! $alias) {
                    throw new \Exception('services not found: ' . $v);
                }

                array_unshift($arguments, $this->get($alias));
            }

            unset($instance);
        }

        return $isClosure
            ? $name(...$arguments)
            : new $name(... $arguments);
    }

    /**
     * 通过反射探测参数
     *
     * @param $isClosure
     * @param $object
     * @return array
     */
    public function detection($isClosure, $object)
    {
        $ref = $isClosure
            ? new \ReflectionFunction($object)
            : (new \ReflectionClass($object))->getConstructor();

        if (! $ref) {
            return [];
        }

        $parameter = $ref->getParameters();
        if (count($parameter) == 0) {
            return [];
        }

        $args = [];
        foreach ($parameter as $k => $v) {
            $class = $v->getClass();
            if ($class instanceof \ReflectionClass) {
                $args[$k] = $class->getName();
            }
        }

        return $args;
    }

    /**
     * 检测是否已经注册
     *
     * @param $name
     * @return bool|string
     */
    public function hasBind(string $name)
    {
        return isset($this->register[$name]);
    }

    /**
     * 卸载服务
     *
     * @param  string $name
     * @return void
     */
    public function remove($name)
    {
        unset($this->register[$name]);
        unset($this->shared[$name]);
        unset($this->alias[$name]);
    }

    /**
     * 注册服务
     *
     * @param  string  $name
     * @param  object/closure/string  $class
     * @param  boolean $shared
     * @return void
     */
    public function bind($name, $class, $shared = false)
    {
        $isClosure = $class instanceof \Closure;

        if ( ! $isClosure and is_object($class)) {
            $this->shared[$name] = $class;

            if ( ! isset($this->register[$name])) {
                $this->register[$name] = [
                    'class'  => get_class($class),
                    'shared' => true
                ];
            }
        }
        else {
            $this->register[$name] = [
                'class' => $class,
                'shared' => $shared
            ];
        }

        if (! $isClosure) {
            $this->alias($name, $this->register[$name]['class']);
        }
    }

    /**
     * 设置别名
     *
     * @param $alias
     * @param $name
     */
    public function alias($alias, $name)
    {
        $this->alias[$name] = $alias;
    }

    /**
     * 获取容器实例
     *
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * 设置全局唯一容器实例
     *
     * @param null $instance
     * @return null
     */
    public static function setInstance($instance = null)
    {
        return static::$instance = $instance;
    }

    /**
     * @param mixed $key
     * @return bool|string
     */
    public function offsetExists($key)
    {
        return $this->hasBind($key);
    }

    /**
     * @param mixed $key
     * @param mixed $val
     */
    public function offsetSet($key, $val)
    {
        if (! $val instanceof \Closure) {
            $val = function () use ($val) {
                return $val;
            };
        }

        $this->bind($key, $val);
    }

    /**
     * @param mixed $key
     * @return null|object
     */
    public function offsetGet($key)
    {
        return $this->get($key);
    }

    /**
     * @param mixed $key
     */
    public function offsetUnset($key)
    {
        $this->remove($key);
    }
}
