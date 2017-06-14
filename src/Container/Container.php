<?php

namespace Flower\Container;

use ArrayAccess;
use Psr\Container\ContainerInterface;

/**
 * Class Container
 *
 * @package Flower\Core
 */
class Container implements ArrayAccess, ContainerInterface
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
    protected $register = [];

    /**
     * 已经实例化的服务
     *
     * @var array
     */
    protected $shared = [];

    /**
     * @param string $name
     * @param array  ...$arguments
     * @return mixed
     * @throws NotFoundException
     */
    public function get($name, ...$arguments)
    {
        if (isset($this->shared[$name])) {
            return $this->shared[$name];
        }

        if (! isset($this->register[$name])) {
            throw new NotFoundException('services not found: ' . $name);
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
     * @param string $name
     * @param array  $arguments
     * @return mixed
     * @throws ContainerException
     */
    public function make(string $name, array $arguments = [])
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
                    throw new ContainerException('services not found: ' . $v);
                }

                array_unshift($arguments, $this->get($alias));
            }

            unset($instance);
        }

        return $isClosure ? $name(...$arguments) : new $name(... $arguments);
    }

    /**
     * 通过反射探测参数
     *
     * @param bool            $isClosure
     * @param string|\Closure $object
     * @return array
     */
    public function detection(bool $isClosure, $object)
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
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        return isset($this->register[$name]);
    }

    /**
     * 卸载服务
     *
     * @param  string $name
     * @return void
     */
    public function remove(string $name)
    {
        unset($this->register[$name]);
        unset($this->shared[$name]);
        unset($this->alias[$name]);
    }

    /**
     * 注册服务
     *
     * @param  string          $name
     * @param  \Closure|string $class
     * @param  bool            $shared
     * @return void
     */
    public function register(string $name, $class, bool $shared = false)
    {
        $isClosure = $class instanceof \Closure;

        if (! $isClosure and is_object($class)) {
            $this->shared[$name] = $class;

            if (! isset($this->register[$name])) {
                $this->register[$name] = [
                    'class'  => get_class($class),
                    'shared' => true,
                ];
            }
        } else {
            $this->register[$name] = [
                'class'  => $class,
                'shared' => $shared,
            ];
        }

        if (! $isClosure) {
            $this->alias($name, $this->register[$name]['class']);
        }
    }

    /**
     * 设置别名
     *
     * @param string $alias
     * @param string $name
     */
    public function alias(string $alias, string $name)
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
     * @param object $instance
     */
    public static function setInstance($instance = null)
    {
        static::$instance = $instance;
    }

    /**
     * @param mixed $key
     * @return bool|string
     */
    public function offsetExists($key)
    {
        return $this->has($key);
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

        $this->register($key, $val);
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
