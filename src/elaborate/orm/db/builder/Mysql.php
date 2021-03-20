<?php

namespace elaborate\orm\db\builder;

use elaborate\orm\db\PDOConnection;

/**
 * mysql驱动
 */
class Mysql extends PDOConnection
{
    /**
     * 解析DSN
     *
     * @param array $config
     * @return string
     */
    protected function parseDsn(array $config = []): string
    {
        if (!empty($config['hostport'])) {
            $dsn = 'mysql:host=' . $config['hostname'] . ';port=' . $config['hostport'];
        } else {
            $dsn = 'mysql:host=' . $config['hostname'];
        }
        $dsn .= ';dbname=' . $config['database'];

        if (!empty($config['charset'])) {
            $dsn .= ';charset=' . $config['charset'];
        }

        return $dsn;
    }

    protected function parseSql()
    {
        
    }
}
