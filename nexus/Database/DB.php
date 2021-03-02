<?php

namespace Nexus\Database;

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
        $this->driver->query("SET NAMES UTF8");
        $this->driver->query("SET collation_connection = 'utf8_general_ci'");
        $this->driver->query("SET sql_mode=''");
        $this->isConnected = true;
        return true;
    }

    public function autoConnect()
    {
        if ($this->isConnected()) {
            return null;
        }
        $config = config('database.mysql');
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
        if (empty($table) || empty($data) || !is_array($data) || empty($whereStr)) {
            throw new DatabaseException("require table and data(array) and whereStr.");
        }
        $updateArr = [];
        foreach ($data as $field => $value) {
            $updateArr[] = "`$field` = " . sqlesc($value);
        }
        $sql = sprintf("update `%s` set %s where %s", $table, implode(', ', $updateArr), $whereStr);
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

}