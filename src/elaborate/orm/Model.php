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

    protected $origin;

    protected $name;

    protected $table;

    /**
     * 主键
     *
     * @var string
     */
    protected $pk = 'id';

    protected $connection = '';

    protected static $db;

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
    public function __construct(array $data = [])
    {
        $this->data = $data;

        if (empty($this->name)) {
            // 当前模型名   
            $name       = str_replace('\\', '/', static::class);
            $this->name = basename($name);
        }
        $this->origin = $this->data;
        
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

    public function newInstance(array $data = [])
    {
        $model = new static($data);

        return $model;
    }

    public static function setDB(Db $db)
    {
        self::$db = $db;
    }

    public function db()
    {
        $query = self::$db->connect($this->connection)->table($this->table ? : $this->name);

        $query->model($this);

        return $query;
    }

    public function save()
    {
    }

    public function destory()
    {

    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->db(), $method], $args);
    }

    public static function __callStatic($method, $args)
    {
        $model = new static();

        return call_user_func_array([$model->db(), $method], $args);
    }
}
