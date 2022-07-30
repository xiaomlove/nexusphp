<?php

namespace Nexus\Database;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class NexusDB
{
    private $driver;

    private static $instance;

    /**
     * @var \Illuminate\Database\Connection
     */
    private static $eloquentConnection;

    private $isConnected = false;

    private function __construct()
    {

    }

    private function __clone()
    {

    }

    const ELOQUENT_CONNECTION_NAME = 'mysql';

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
        if (!IN_NEXUS) {
            return DB::table($table)->insertGetId($data);
        }
        $fields = array_map(function ($value) {return "`$value`";}, array_keys($data));
        $values = array_map(function ($value) {return sqlesc($value);}, array_values($data));
        $sql = sprintf("insert into `%s` (%s) values (%s)", $table, implode(', ', $fields), implode(', ', $values));
        sql_query($sql);
        return mysql_insert_id();
    }

    public static function update($table, $data, $whereStr)
    {
        if (!IN_NEXUS) {
            return DB::table($table)->whereRaw($whereStr)->update($data);
        }
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
        if (!IN_NEXUS) {
            $query = DB::table($table)->whereRaw($whereStr);
            if ($limit !== null) {
                $query->limit($limit);
            }
            return $query->delete();
        }
        $sql = "delete from $table where $whereStr";
        if (!is_null($limit)) {
            $sql .= " limit $limit";
        }
        sql_query($sql);
        return mysql_affected_rows();
    }

    public static function getOne($table, $whereStr, $fields = '*')
    {
        if (!IN_NEXUS) {
            $result = DB::table($table)->whereRaw($whereStr)->selectRaw($fields)->first();
            return $result ? json_decode(json_encode($result), true) : null;
        }
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
        if (!IN_NEXUS) {
            $result = DB::table($table)->whereRaw($whereStr)->selectRaw($fields)->get();
            if ($result->isEmpty()) {
                return [];
            }
            return json_decode(json_encode($result), true);
        }
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
        return self::select($sql);
    }

    public static function select(string $sql)
    {
        if (!IN_NEXUS) {
            $result = DB::select($sql);
            return json_decode(json_encode($result), true);
        }
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
        $connection = self::$eloquentConnection = $capsule->getConnection($connectionName);
        $connection->enableQueryLog();
        $connection->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }

    private static function schema(): \Illuminate\Database\Schema\Builder
    {
        if (IN_NEXUS) {
            return Capsule::schema(self::ELOQUENT_CONNECTION_NAME);
        }
        throw new \RuntimeException('can not call this when not in nexus.');
    }

    public static function hasTable($table): bool
    {
        if (IN_NEXUS) {
            return self::schema()->hasTable($table);
        }
        return Schema::hasTable($table);
    }

    public static function hasColumn($table, $column): bool
    {
        if (IN_NEXUS) {
            return self::schema()->hasColumn($table, $column);
        }
        return Schema::hasColumn($table, $column);
    }

    public static function table($table): \Illuminate\Database\Query\Builder
    {
        if (IN_NEXUS) {
            return Capsule::table($table, null, self::ELOQUENT_CONNECTION_NAME);
        }
        return DB::table($table);
    }

    public static function raw($value): \Illuminate\Database\Query\Expression
    {
        if (IN_NEXUS) {
            return new Expression($value);
        }
        return DB::raw($value);
    }

    public static function statement($value)
    {
        if (IN_NEXUS) {
            return sql_query($value);
        }
        return DB::statement($value);
    }

    public static function transaction(\Closure $callback, $attempts = 1)
    {
        if (IN_NEXUS) {
            return Capsule::connection(self::ELOQUENT_CONNECTION_NAME)->transaction($callback, $attempts);
        }
        return DB::transaction($callback, $attempts);
    }

    public static function remember($key, $ttl, \Closure $callback)
    {
        if (IN_NEXUS) {
            global $Cache;
            $result = $Cache->get_value($key);
            if ($result === false) {
                $result = $callback();
                do_log("cache miss [$key]", 'debug');
                $Cache->cache_value($key, $result, $ttl);
            } else {
                do_log("cache hit [$key]", 'debug');
            }
            return $result;
        } else {
            return Cache::remember($key, $ttl, $callback);
        }
    }

    public static function cache_put($key, $value, $ttl = 3600)
    {
        if (IN_NEXUS) {
            global $Cache;
            return $Cache->cache_value($key, $value, $ttl);
        } else {
            return Cache::put($key, $value, $ttl);
        }
    }

    public static function cache_get($key)
    {
        if (IN_NEXUS) {
            global $Cache;
            return $Cache->get_value($key);
        } else {
            return Cache::get($key);
        }
    }

    public static function cache_del($key)
    {
        if (IN_NEXUS) {
            global $Cache;
            $Cache->delete_value($key, true);
        } else {
            Cache::forget($key);
            $langList = get_langfolder_list();
            foreach ($langList as $lf) {
                Cache::forget($lf . '_' . $key);
            }
        }
    }

    public static function cache_del_by_pattern($pattern)
    {
        $redis = self::redis();
        $it = NULL;
        do {
            // Scan for some keys
            $arr_keys = $redis->scan($it, $pattern);

            // Redis may return empty results, so protect against that
            if ($arr_keys !== FALSE) {
                foreach($arr_keys as $str_key) {
                    do_log("[SCAN_KEY] $str_key");
                    self::cache_del($str_key);
                }
            }
        } while ($it > 0);
    }

    /**
     * @return mixed|\Redis|null
     */
    public static function redis()
    {
        if (IN_NEXUS) {
            global $Cache;
            return $Cache->getRedis();
        } else {
            return Redis::connection()->client();
        }
    }

    public static function getMysqlColumnInfo($table, $column = null)
    {
        static $driver;
        $config = nexus_config('nexus.mysql');
        if (is_null($driver)) {
            $driver = new DBMysqli();
            $driver->connect($config['host'], $config['username'], $config['password'], 'information_schema', $config['port']);
        }
        $sql = sprintf(
            "select * from COLUMNS where TABLE_SCHEMA = '%s' and TABLE_NAME = '%s'",
            $config['database'], $table
        );
        if ($column !== null) {
            $sql .= " and COLUMN_NAME = '$column'";
        }
        $res = $driver->query($sql);
        if ($column !== null) {
            return $driver->fetchAssoc($res);
        }
        $results = [];
        while ($row = $driver->fetchAssoc($res)) {
            $results[$row['COLUMN_NAME']] = $row;
        }
        return $results;

    }


}
