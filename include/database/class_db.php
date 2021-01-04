<?php

class DB
{
    private $driver;

    private static $instance;

    private static $queries = [];

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
        return $this->driver->connect($host, $username, $password, $database, $port);
    }

    public function query(string $sql)
    {
        try {
            return $this->driver->query($sql);
        } catch (\Exception $e) {
            throw new \DatabaseException($sql, $e->getMessage());
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
        return $this->driver->escapeString($string);
    }

    public function lastInsertId()
    {
        return $this->driver->lastInsertId();
    }





}