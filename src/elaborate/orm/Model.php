<?php

namespace elaborate\orm;

/**
 * 模型
 */
class Model
{
    /**
     * 模型数据
     *
     * @var array
     */
    protected $data;

    protected $table;

    /**
     * 主键
     *
     * @var string
     */
    protected $pk = 'id';

    protected $connection;

    /**
     * 实例过的模型
     *
     * @var array
     */
    protected static $initialize = [];

    /**
     * 构造函数
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;

        $this->initialize();
    }

    private function initialize(): void
    {
        if (!isset(static::$initialize[static::class])) {
            static::$initialize[static::class] = true;
            static::init();
        }
    }

    protected static function init()
    {
    }

    public function newInstance()
    {

    }

    public function setDB($db)
    {
        
    }

    public function db()
    {
    }

    public function where()
    {
    }

    public function find()
    {
    }

    public function select()
    {
    }

    public function save()
    {
    }

    public function destory()
    {

    }
}
