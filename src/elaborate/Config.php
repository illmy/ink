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

    protected $ext = 'php';

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

    /**
     * 加载配置
     *
     * @param string $file
     * @param string $name
     * @return void
     */
    public function load(string $file, string $name = '')
    {
        if (is_file($file)) {
            $filename = $file;
        } elseif (is_file($this->path . $file . $this->ext)) {
            $filename = $this->path . $file . $this->ext;
        }

        if (isset($filename)) {
            $config = include $filename;
            return is_array($config) ? $this->set($name, $config) : [];
        }

        return $this->config;
    }

    /**
     * 获取配置
     *
     * @param string $key
     * @param string $default
     * @return void
     */
    public function get(string $key = null, string $default = null)
    {
        if (empty($key)) {
            return $this->configs;
        }

        if (strpos($key, '.') === false) {
            return $this->configs[$key] ?? [];
        }

        $key = explode('.', $key);
        $config = $this->configs;

        // 按.拆分成多维数组进行判断
        foreach ($key as $val) {
            if (isset($config[$val])) {
                $config = $config[$val];
            } else {
                return $default;
            }
        }
        return $config;
    }

    /**
     * 设置配置
     *
     * @param string $key      建名
     * @param array $config    值
     * @return void
     */
    public function set(string $key, array $config)
    {
        if (!empty($key)) {
            if (isset($this->config[$key])) {
                $result = array_merge($this->config[$key], $config);
            } else {
                $result = $config;
            }

            $this->config[$key] = $result;
        } else {
            $result = $this->config = array_merge($this->config, array_change_key_case($config));
        }

        return $result;
    }

    public function has(string $key)
    {
        throw new \Exception("未实现");
    }

    public function delete(string $key)
    {
        throw new \Exception("未实现");
    }

    function offsetGet($key)
    {
        return $this->get($key);
    }

    function offsetSet($key, $value)
    {
        return $this->set($key, $value);
    }

    function offsetExists($key)
    {
        return $this->has($key);
    }

    function offsetUnset($key)
    {
        return $this->delete($key);
    }
}
