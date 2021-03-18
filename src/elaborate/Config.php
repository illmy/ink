<?php

namespace elaborate;

/**
 * 配置类
 */
class Config implements \ArrayAccess
{
    /**
     * 配置文件路径
     *
     * @var string
     */
    protected $path;

    /**
     * 配置参数
     *
     * @var array
     */
    protected $configs = [];

    /**
     * 构造函数
     *
     * @param string $path 路径
     */
    public function __construct(string $path = null)
    {
        $this->path = $path;
    }

    public function load()
    {

    }

    public function get()
    {
        
    }

    function offsetGet($key)
    {
        return $this->configs[$key];
    }

    function offsetSet($key, $value)
    {
        throw new \Exception("不能写配置文件");
    }

    function offsetExists($key)
    {
        return isset($this->configs[$key]);
    }

    function offsetUnset($key)
    {
        unset($this->configs[$key]);
    }
}
