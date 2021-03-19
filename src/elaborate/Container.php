<?php

namespace elaborate;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use elaborate\exception\ClassNotFoundException;
use elaborate\exception\InvalidArgumentException;

/**
 * 容器
 */
class Container
{
    /**
     * 容器对象实例
     *
     * @var Container
     */
    public static $instance;

    /**
     * 绑定标识
     *
     * @var array
     */
    public $bindings = [];

    /**
     * 容器中的对象实例
     *
     * @var array
     */
    public $instances = [];

    /**
     * 获取当前容器的实例（单例）
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public static function setInstance()
    {
    }

    /**
     * 获取容器中的对象实例
     * @param string $abstract 类名或者标识
     * @return object
     */
    public function get($abstract)
    {
        if ($this->has($abstract)) {
            return $this->make($abstract);
        }

        throw new ClassNotFoundException('class not exists: ' . $abstract, $abstract);
    }

    /**
     * 绑定一个类、实例到容器
     *
     * @param string $abstract  标识
     * @param mixed  $concrete  类、实例
     * @return $this
     */
    public function bind($abstract, $concrete = null)
    {
        if (is_object($concrete)) {
            $this->instance($abstract, $concrete);
        } else {
            $abstract = $this->getRealBind($abstract);
            if ($abstract != $concrete) {
                $this->bindings[$abstract] = $concrete;
            }
        }

        return $this;
    }

    /**
     * 根据别名获取真实类名
     * @param  string $abstract
     * @return string
     */
    public function getRealBind($abstract)
    {
        if (isset($this->bindings[$abstract])) {
            $bind = $this->bindings[$abstract];

            if (is_string($bind)) {
                return $this->getRealBind($bind);
            }
        }

        return $abstract;
    }

    /**
     * 判断容器中是否存在类及标识
     * @access public
     * @param string $abstract 类名或者标识
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * 判断容器中是否存在类及标识
     * @access public
     * @param string $name 类名或者标识
     * @return bool
     */
    public function has($name): bool
    {
        return $this->bound($name);
    }

    /**
     * 绑定实例到容器
     *
     * @param string $abstract  标识
     * @param object $instance  实例
     * @return $this
     */
    public function instance(string $abstract, $instance)
    {
        $this->instances[$abstract] = $instance;

        return $this;
    }

    /**
     * 创建实例
     *
     * @param string $abstract 标识
     * @param array  $vars     参数
     * @return object
     */
    public function make($abstract, array $vars = [])
    {
        $abstract = $this->getRealBind($abstract);
        
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $object = $this->invokeClass($abstract);

        $this->instances[$abstract] = $object;

        return $object;
    }

    /**
     * 调用反射执行类的方法 支持参数绑定
     * @access public
     * @param object $instance 对象实例
     * @param mixed  $reflect  反射类
     * @param array  $vars     参数
     * @return mixed
     */
    public function invokeReflectMethod($instance, $reflect, array $vars = [])
    {
        $params = $reflect->getParameters();
        $args = $this->resolveDependencies($params, $vars);

        return $reflect->invokeArgs($instance, $args);
    }

    /**
     * 调用反射执行类的实例化
     *
     * @param string $class  类名
     * @param array  $vars   变量
     * @return object
     */
    public function invokeClass($class, array $vars = [])
    {
        try {
            $reflector = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new ClassNotFoundException('class not exists: ' . $class, $class);
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return $reflector->newInstance();
        } else {
            $dependencies = $constructor->getParameters();
            $instances = $this->resolveDependencies($dependencies, $vars);
            return $reflector->newInstanceArgs($instances);
        }
    }

    /**
     * 依赖参数
     *
     * @param array $dependencies 参数
     * @param array $vars         变量
     * @return array
     */
    protected function resolveDependencies(array $dependencies, array $vars = [])
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            $result = is_null(self::getParameterClassName($dependency))
                ? $this->resolvePrimitive($dependency, $vars)
                : $this->resolveClass($dependency);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * 获取类名根据参数类型
     *
     * @param \ReflectionParameter $parameter
     * @return string|null
     */
    public static function getParameterClassName($parameter)
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return;
        }

        $name = $type->getName();

        return $name;
    }

    /**
     * 解析非非类参数
     *
     * @param ReflectionParameter $parameter 参数
     * @param array               $vars      变量    
     * @return mixed
     */
    protected function resolvePrimitive(ReflectionParameter $parameter, array &$vars)
    {
        reset($vars);
        $name = $parameter->getName();
        $lowerName      = mb_strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . '_', $name), 'UTF-8');
        $type = key($vars) === 0 ? 1 : 0;
        if (1 == $type && !empty($vars)) {
            $args = array_shift($vars);
        } elseif (0 == $type && array_key_exists($name, $vars)) {
            $args = $vars[$name];
        } elseif (0 == $type && array_key_exists($lowerName, $vars)) {
            $args = $vars[$lowerName];
        } elseif ($parameter->isDefaultValueAvailable()) {
            $args = $parameter->getDefaultValue();
        } else {
            throw new InvalidArgumentException('丢失方法参数' . $name);
        }

        return $args;
    }

    /**
     * 解析类参数
     *
     * @param ReflectionParameter $parameter 参数
     * @return object
     */
    protected function resolveClass(ReflectionParameter $parameter)
    {
        $className = self::getParameterClassName($parameter);
        return $this->make($className);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        $this->bind($name, $value);
    }
}
