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

    protected $leftJoin = [];

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
            $params = $config['params'] + $this->params;
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

    public function getLastSql()
    {
        return $this->queryStr;
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

    public function join(string $table, string $left, string $right)
    {
        array_push($this->join, [$table, $left, $right]);

        return $this;
    }

    public function leftJoin(string $table, string $left, string $right)
    {
        array_push($this->leftJoin, [$table, $left, $right]);

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

        $this->leftJoin = [];

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
                $join[] = "INNER JOIN {$leftTable} ON {$left} = {$right}";
            }
        }
        if (!empty($this->leftJoin)) {
            foreach ($this->leftJoin as $key => $value) {
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

        $result = $this->execute($sql, $this->bind);
        if ($result) {
            $result = $this->connect()->lastInsertId();
        }
        $this->clearQuery();

        return $result;
    }

    private function parseInsertSql(array $data = [])
    {
        $insertSql = "INSERT INTO %TABLE% (%FIELD%) VALUES (%DATA%)";

        $fields = array_keys($data);
        $values = array_values($data);

        $values = array_map(
            function ($v) {
                if (is_numeric($v)) {
                    return $v;
                }
                return "'{$v}'";
            },
            $values
        );
        $this->bind = array_merge($this->bind, $data);
        return str_replace(
            ['%TABLE%', '%FIELD%', '%DATA%'],
            [
                $this->table,
                implode(', ', $fields),
                ':' . implode(', :', $fields)
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
        $this->clearQuery();

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
            $set[] = $key . ' = ' . "'{$val}'";
        }

        return str_replace(
            ['%TABLE%', '%SET%', '%WHERE%'],
            [
                $this->table,
                implode(' , ', $set),
                $this->parseWhere()
            ],
            $updateSql
        );
    }

    public function delete()
    {
        $sql = $this->parseDeleteSql();
        $result = $this->execute($sql, $this->bind);
        $this->clearQuery();

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
            $deleteSql
        );
    }

    protected function parseWhere()
    {
        $where = [];
        foreach ($this->where as $value) {
            $field = $value[0];
            $ffield = str_replace('.', '', $field);

            if (strpos($field, '.')) {
                [$tb, $fd] = explode('.', $field);
                $field = $tb . '.' . "`{$fd}`";
            } else {
                $field = "`{$field}`";
            }

            if ($value[1] == 'in') {
                $where[] = $this->parseIn($field, $value[2]);
            } else if($value[1] == 'like') {
                $likeValue = $value['2'];
                $where[] = $field . ' ' . $value[1] . ' ' . "'{$likeValue}'";
            } else {
                $ffield = $this->bindVar($value['2'], $ffield);
                $where[] = $field . ' ' . $value[1] . ':' . $ffield;
            }
        }

        return implode(' and ', $where);
    }

    protected function parseIn($key, $value)
    {
        if (!is_array($value)) {
            $value = explode(',', $value);
        }

        foreach ($value as $v) {
            $name    = $this->bindVar($v);
            $array[] = ':' . $name;
        }

        if (count($array) == 1) {
            return $key . ' = ' . $array[0];
        } else {
            $value = implode(',', $array);
        }
        return $key . ' ' . 'IN' . ' (' . $value . ')';
    }

    protected function bindVar($value, $name = null)
    {
        $name = $name ?: 'InkBind_' . (count($this->bind) + 1) . '_' . mt_rand() . '_';
        $this->bind[$name] = $value;
        return $name;
    }

    /**
     * 解析pdo连接的dsn信息
     * @param array $config 连接信息
     * @return string
     */
    abstract protected function parseDsn(array $config): string;
}
