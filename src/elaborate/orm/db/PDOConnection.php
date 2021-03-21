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

    protected $table;

    protected $params = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ];

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

    protected $orderBy = [];

    protected $limit = [];

    protected $join = [];

    protected $groupBy = '';

    protected $alias = '';

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

    public function table(string $table)
    {
        $this->table = $table;

        return $this;
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

        // if (!empty($this->model)) {
        //     $this->resultToModel($result);
        // }

        return $result;
    }

    protected function resultToModel(array &$result = [])
    {
        foreach ($result as &$value) {
            $value = $this->model->newInstance($value);
        }
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
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return $this
     */
    public function where(string $field, string $operator, $value)
    {
        $where = [$field, $operator, $value];
        array_push($this->where, $where);

        return $this;
    }

    public function field(array $field = [])
    {
        $this->field = $field;

        return $this;
    }

    public function groupBy(string $field)
    {
        $this->groupBy = $field;
        return $this;
    }

    public function orderBy(string $orderField, string $orderWay = 'ASC')
    {
        $this->orderBy = [$orderField, $orderWay];
        return $this;
    }

    public function limit(int $start = 0, int $limit = 0)
    {
        if (empty($limit)) {
            $this->limit = [$start];
        } else {
            $this->limit = [$start, $limit];
        }

        return $this;
    }

    public function leftJoin(string $table, string $left, string $right)
    {
        $this->join = array_merge($this->join, [$table, $left, $right]);

        return $this;
    }

    public function alias(string $name)
    {
        $this->alias = $name;
        return $this;
    }

    public function find()
    {
        $this->limit(1);
        $sql = $this->parseQuerySql();

        $result = $this->query($sql, $this->bind);
        
        $this->clearQuery();

        return array_shift($result);
    }

    public function clearQuery()
    {
        $this->where = [];

        $this->field = [];

        $this->orderBy = [];

        $this->limit = [];

        $this->join = [];

        $this->groupBy = '';

        $this->alias = '';
        
        $this->bind = [];
    }

    public function select()
    {
        $sql = $this->parseQuerySql();

        $result = $this->query($sql, $this->bind);

        $this->clearQuery();

        return $result;
    }

    private function parseQuerySql()
    {
        $querySql = "SELECT %FIELD% FROM %TABLE% %ALIAS% %JOIN% WHERE %WHERE% %GROUP% %ORDER% %LIMIT%";

        $fields = empty($this->field) ? '*' : implode(',', $this->field);

        $join = [];
        if (!empty($this->join)) {
            foreach ($this->join as $key => $value) {
                [$leftTable, $left, $right] = $value;
                $join[] = "LEFT JOIN {$leftTable} ON {$left} = {$right}";
            }
        }
        $join = implode(' ', $join);

        $groupBy = '';
        if (!empty($this->groupBy)) {
            $groupBy = "GROUP BY {$this->groupBy}";
        }

        $orderBy = '';
        if (!empty($this->orderBy)) {
            [$field, $way] = $this->orderBy;
            $orderBy = "ORDER BY {$field} {$way}";
        }

        $limit = '';
        if (!empty($this->limit)) {
            if (isset($this->limit[1])) {
                [$start, $lm] = $this->limit;
                $limit = "limit {$start}, {$lm}";
            } else {
                $lm = $this->limit[0];
                $limit = "limit {$lm}";
            }
        }

        $alias = '';
        if (!empty($this->alias)) {
            $alias = "as {$this->alias}"; 
        }
        return str_replace(
            ['%FIELD%', '%TABLE%', '%ALIAS%', '%JOIN%', '%WHERE%', '%GROUP%', '%ORDER%', '%LIMIT%'],
            [
                $fields,
                $this->table,
                $alias,
                $join,
                $this->parseWhere(),
                $groupBy,
                $orderBy,
                $limit
            ],
            $querySql
        );
    }

    /**
     * 插入数据
     *
     * @param array $data
     * @return int
     */
    public function insert(array $data = [])
    {
        $sql = $this->parseInsertSql($data);
        $result = $this->execute($sql);
        if ($result) {
            $result = $this->connect()->lastInsertId();
        }
        return $result;
    }

    private function parseInsertSql(array $data = [])
    {
        $insertSql = "INSERT INTO %TABLE% (%FIELD%) VALUES (%DATA%)";

        $fields = array_keys($data);
        $values = array_values($data);

        return str_replace(
            ['%TABLE', '%FIELD%', '%DATA%'],
            [
                $this->table,
                implode(', ', $fields),
                implode(', ', $values)
            ],
            $insertSql
        );
    }

    /**
     * 更新数据
     *
     * @param array $data
     * @return int
     */
    public function update(array $data = [])
    {
        $sql = $this->parseUpdateSql($data);
        $result = $this->execute($sql, $this->bind);

        return $result;
    }

    private function parseUpdateSql(array $data)
    {
        $updateSql = "UPDATE %TABLE% SET %SET% WHERE %WHERE%";

        if (empty($data)) {
            return '';
        }

        $set = [];
        foreach ($data as $key => $val) {
            $set[] = $key . ' = ' . $val;
        }

        return str_replace(
            ['%TABLE%', '%SET%', '%WHERE%'],
            [
                $this->table,
                implode(' , ', $set),
                $this->parseWhere()
            ],
            $updateSql);
    }

    public function delete()
    {
        $sql = $this->parseDeleteSql();
        $result = $this->execute($sql, $this->bind);

        return $result;
    }

    private function parseDeleteSql()
    {
        $deleteSql = "DELETE FROM %TABLE% WHERE %WHERE%";

        return str_replace(
            ['%TABLE%', '%WHERE%'],
            [
                $this->table,
                $this->parseWhere()
            ],
            $deleteSql);

    }

    private function parseWhere()
    {
        $where = [];
        $bind = [];
        foreach ($this->where as $value) {
            $where[] = $value[0] . ' ' . $value[1] . ' :' . $value[0];
            $bind[$value[0]] = $value[2];
        }
        $this->bind = array_merge($this->bind, $bind);

        return implode(' and ', $where);
    }

    /**
     * 解析pdo连接的dsn信息
     * @param array $config 连接信息
     * @return string
     */
    abstract protected function parseDsn(array $config): string;
}
