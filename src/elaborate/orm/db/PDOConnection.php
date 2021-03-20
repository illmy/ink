<?php 
namespace elaborate\orm\db;

use PDO;
use elaborate\orm\Db;
use elaborate\orm\Model;

abstract class PDOConnection
{
    /**
     * 数据库链接配置
     *
     * @var array
     */
    protected $config = [];

    protected $params = [];

    /**
     * 链接记录
     *
     * @var array
     */
    protected $links = [];

    protected $PDOStatement;

    protected $queryStr = [];

    protected $bind = [];

    protected $numRows = 0;

    protected $db;

    protected $model;

    protected $where = [];

    protected $field = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * 链接数据库
     *
     * @param array $config
     * @param integer $linkCode
     * @return PDO
     */
    public function connect(array $config = [], $linkCode = 0)
    {
        if (isset($this->links[$linkCode])) {
            return $this->links[$linkCode];
        }

        $config = array_merge($this->config, $config);

        if (isset($config['params']) && is_array($config['params'])) {
            $params = array_merge($this->params, $config['params']);
        } else {
            $params = $this->params;
        }

        try {
            if (empty($config['dsn'])) {
                $config['dsn'] = $this->parseDsn($config);
            }

            $this->links[$linkCode] = $this->createPdo($config['dsn'], $config['username'], $config['password'], $params);

            return $this->links[$linkCode];
        } catch (\PDOException $e) {
            throw $e;
        }

    }

    public function getPDOStatement(string $sql, array $bind = [])
    {
        try {
            $link = $this->connect();
            // 记录SQL语句
            $this->queryStr = $sql;
            $this->bind     = $bind;

            // 预处理
            $this->PDOStatement = $link->prepare($sql);

            // 绑定参数
            $this->bindValue($bind);

            // 执行查询
            $this->PDOStatement->execute();

            return $this->PDOStatement;
        } catch (\Throwable | \Exception $e) {

            throw $e;
        }
    }

    /**
     * 绑定参数
     *
     * @param array $bnid
     * @return void
     */
    public function bindValue(array $bind = [])
    {
        foreach ($bind as $key => $val) {
            // 占位符
            $param = is_numeric($key) ? $key + 1 : ':' . $key;

            $result = $this->PDOStatement->bindValue($param, $val);

            if (!$result) {
                throw new \PDOException('参数绑定错误');
            }
        }
    }

    /**
     * pdo实例
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param string $params
     * @return PDO
     */
    protected function createPdo($dsn, $username, $password, $params)
    {
        return new PDO($dsn, $username, $password, $params); 
    }

    /**
     * 执行查询 返回数据集
     * @param string $sql    sql指令
     * @param array  $bind   参数绑定
     * @return array|Model
     */
    public function query(string $sql, array $bind = []): array
    {
        $this->getPDOStatement($sql, $bind);

        $result = $this->getResult();

        if (!empty($this->model)) {
            $this->resultToModel($result);
        }

        return $result;
    }

    protected function resultToModel(array $result = [])
    {
        $this->model->newInstance($result);
    }

    /**
     * 获取数据集数组
     *
     * @return array
     */
    public function getResult()
    {
        $result = $this->PDOStatement->fetchAll(PDO::FETCH_ASSOC);

        $this->numRows = count($result);

        return $result;
    }

    /**
     * 执行语句
     * @param string $sql  sql指令
     * @param array  $bind 参数绑定
     * @return int
     */
    public function execute(string $sql, array $bind = []): int
    {
        $this->getPDOStatement($sql, $bind);

        $this->numRows = $this->PDOStatement->rowCount();

        return $this->numRows;
    }

    public function model(Model $model)
    {
        $this->model = $model;
    }

    /**
     * 设置数据库管理
     *
     * @param Db $db
     * @return void
     */
    public function setDb(Db $db)
    {
        $this->db = $db;    
    }

    /**
     * 设置查询条件
     *
     * @param array $where
     * @return $this
     */
    public function where(array $where = [])
    {
        $this->where = array_merge($this->where, $where);

        return $this;
    }

    public function field(array $field = [])
    {
        $this->field = $field;

        return $this;
    }

    public function find()
    {
        $this->prareSql();
    }

    public function select()
    {
    }

    /**
     * 解析pdo连接的dsn信息
     * @param array $config 连接信息
     * @return string
     */
    abstract protected function parseDsn(array $config): string;

    /**
     * 解析sql
     *
     * @return string
     */
    abstract protected function parseSql(): string;
}