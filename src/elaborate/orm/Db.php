<?php

namespace elaborate\orm;

use elaborate\Config;
use elaborate\exception\InvalidArgumentException;
use elaborate\orm\db\PDOConnection;

/**
 * 数据库管理类
 */
class Db
{
    protected $instance = [];

    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * 创建数据库链接
     *
     * @param string $name
     * @return PDOConnection
     */
    public function connect(string $name = ''): PDOConnection
    {
        if (empty($name)) {
            $name = $this->getConfig('default', 'mysql');
        }

        if (!isset($this->instance[$name])) {
            $this->instance[$name] = $this->newConnection($name);
        }

        return $this->instance[$name];
    }

    /**
     * 新链接
     *
     * @param string $name
     * @return PDOConnection
     */
    private function newConnection(string $name)
    {
        $connections = $this->getConfig('connections');
        if (empty($connections[$name])) {
            throw new InvalidArgumentException('链接不存在');
        }

        $config = $connections[$name];

        $type = !empty($config['type']) ? $config['type'] : 'mysql';

        $class = 'elaborate\\orm\\db\\builder\\' . studly($type);

        $connection = new $class($config);
        $connection->setDb($this);

        return $connection;
    }

    /**
     * 获取数据库配置
     *
     * @param string $name
     * @return array|string
     */
    public function getConfig(string $name = '', string $default = null)
    {
        if (empty($name)) {
            return $this->config->get('database', []);
        }
        return $this->config->get('database.' . $name, $default);
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->connect(), $method], $args);
    }

}
