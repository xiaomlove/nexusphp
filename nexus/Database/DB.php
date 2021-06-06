<?php

namespace Nexus\Database;

use Illuminate\Database\Capsule\Manager as Capsule;

class DB
{
    private $driver;

    private static $instance;

    private $isConnected = false;

    private function __construct()
    {

    }

    private function __clone()
    {

    }

    const ELOQUENT_CONNECTION_NAME = 'default';

    public function setDriver(DBInterface $driver)
    {
        $this->driver = $driver;

        return $this;
    }

    public function getDriver()
    {
        return $this->driver;
    }

    public static function getInstance()
    {
        if (self::$instance) {
            return self::$instance;
        }
        $instance = new self;
        $driver = new DBMysqli();
        $instance->setDriver($driver);
        return self::$instance = $instance;
    }

    public function connect($host, $username, $password, $database, $port)
    {
        $result = $this->driver->connect($host, $username, $password, $database, $port);
        if (!$result) {
            throw new DatabaseException(sprintf('[%s]: %s', $this->errno(), $this->error()));
        }
        $this->isConnected = true;
        return true;
    }

    public function autoConnect()
    {
        if ($this->isConnected()) {
            return null;
        }
        $config = nexus_config('nexus.mysql');
        return $this->connect($config['host'], $config['username'], $config['password'], $config['database'], $config['port']);
    }

    public function query(string $sql)
    {
        try {
            $this->autoConnect();
            return $this->driver->query($sql);
        } catch (\Exception $e) {
            do_log(sprintf("%s [%s] %s", $e->getMessage(), $sql, $e->getTraceAsString()));
            throw new DatabaseException($e->getMessage(), $sql);
        }

    }

    public function error()
    {
        return $this->driver->error();
    }

    public function errno()
    {
        return $this->driver->errno();
    }

    public function numRows($result)
    {
        return $this->driver->numRows($result);
    }

    public function select_db($database)
    {
        return $this->driver->selectDb($database);
    }

    public function fetchAssoc($result)
    {
        return $this->driver->fetchAssoc($result);
    }

    public function fetchRow($result)
    {
        return $this->driver->fetchRow($result);
    }

    public function fetchArray($result, $type = null)
    {
        return $this->driver->fetchArray($result, $type);
    }

    public function affectedRows()
    {
        return $this->driver->affectedRows();
    }

    public function escapeString(string $string)
    {
        $this->autoConnect();
        return $this->driver->escapeString($string);
    }

    public function lastInsertId()
    {
        return $this->driver->lastInsertId();
    }

    public function freeResult($result)
    {
        return $this->driver->freeResult($result);
    }

    public function isConnected()
    {
        return $this->isConnected;
    }

    public static function insert($table, $data)
    {
        if (empty($table) || empty($data) || !is_array($data)) {
            throw new DatabaseException("require table and data(array).");
        }
        $fields = array_map(function ($value) {return "`$value`";}, array_keys($data));
        $values = array_map(function ($value) {return sqlesc($value);}, array_values($data));
        $sql = sprintf("insert into `%s` (%s) values (%s)", $table, implode(', ', $fields), implode(', ', $values));
        sql_query($sql);
        return mysql_insert_id();
    }

    public static function update($table, $data, $whereStr)
    {
        $updateArr = [];
        foreach ($data as $field => $value) {
            $updateArr[] = "`$field` = " . sqlesc($value);
        }
        $sql = sprintf("update `%s` set %s where %s", $table, implode(', ', $updateArr), $whereStr);
        sql_query($sql);
        return mysql_affected_rows();
    }

    public static function delete($table, $whereStr, $limit = null)
    {
        $sql = "delete from $table where $whereStr";
        if (!is_null($limit)) {
            $sql .= " limit $limit";
        }
        sql_query($sql);
        return mysql_affected_rows();
    }

    public static function getOne($table, $whereStr, $fields = '*')
    {
        if ($fields != '*') {
            if (is_array($fields)) {
                $fields = implode(', ', $fields);
            }
        }
        if (empty($fields)) {
            do_log("args: " . json_encode(func_get_args()));
            throw new DatabaseException("empty fields.");
        }
        $sql = "select $fields from $table where $whereStr limit 1";
        $res = sql_query($sql);
        return mysql_fetch_assoc($res);
    }

    public static function getAll($table, $whereStr, $fields = '*')
    {
        if ($fields != '*') {
            if (is_array($fields)) {
                $fields = implode(', ', $fields);
            }
        }
        if (empty($fields)) {
            do_log("args: " . json_encode(func_get_args()));
            throw new DatabaseException("empty fields.");
        }
        $sql = "select $fields from $table where $whereStr";
        $res = sql_query($sql);
        $result = [];
        while ($row = mysql_fetch_assoc($res)) {
            $result[] = $row;
        }
        return $result;
    }

    public static function bootEloquent(array $config)
    {
        $capsule = new Capsule;
        $connectionName = self::ELOQUENT_CONNECTION_NAME;
        $capsule->addConnection($config, $connectionName);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $connection = $capsule->getConnection($connectionName);
        $connection->enableQueryLog();
        $connection->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }

    public static function schema(): \Illuminate\Database\Schema\Builder
    {
        return Capsule::schema(self::ELOQUENT_CONNECTION_NAME);
    }

    public static function table($table): \Illuminate\Database\Query\Builder
    {
        return Capsule::table($table);
    }

    public static function getMysqlColumnInfo($table, $column)
    {
        static $driver;
        $config = nexus_config('nexus.mysql');
        if (is_null($driver)) {
            $driver = new DBMysqli();
            $driver->connect($config['host'], $config['username'], $config['password'], 'information_schema', $config['port']);
        }
        $sql = sprintf(
            "select * from COLUMNS where TABLE_SCHEMA = '%s' and TABLE_NAME = '%s' and COLUMN_NAME = '%s'",
            $config['database'], $table, $column
        );
        $res = $driver->query($sql);
        return $driver->fetchAssoc($res);
    }


}
