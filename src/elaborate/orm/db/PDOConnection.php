<?php 
namespace elaborate\orm\db;

use PDO;

abstract class PDOConnection
{
    public function connect()
    {
        
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
}