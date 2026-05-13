<?php

namespace Nexus\Database;

use ClickHouseDB\Client;

class ClickHouse
{
    private static ?Client $client = null;

    public static function getClient(): Client
    {
        if (is_null(self::$client)) {
            $config = nexus_config('clickhouse.connection');
            $client = new Client($config);
            $options = $config['options'];
            $client->database($options['database']);
            //            $client->setTimeout($options['timeout']);
            $client->setConnectTimeOut($options['connectTimeOut']);
            self::$client = $client;
        }

        return self::$client;
    }

    public static function count(string $table, string $whereStr = '', array $binds = []): int
    {
        $start = microtime(true);
        $countAlias = 'count';
        $sql = "select count(*) as $countAlias from $table";
        $whereStr = strtolower(trim($whereStr));
        if ($whereStr) {
            if (! str_starts_with($whereStr, 'where')) {
                $whereStr = "where $whereStr";
            }
            $sql .= " $whereStr";
        }
        $stat = self::getClient()->select($sql, $binds);
        $result = $stat->fetchOne($countAlias) ?? 0;
        $costTime = number_format(microtime(true) - $start, 3);
        do_log("table: $table, whereStr: $whereStr, binds: ".json_encode($binds).", result: $result, cost time: $costTime sec.");

        return $result;
    }

    public static function list(string $sql, array $binds = []): array
    {
        $start = microtime(true);
        $stat = self::getClient()->select($sql, $binds);
        $result = $stat->rows();
        $costTime = number_format(microtime(true) - $start, 3);
        do_log("sql: $sql, binds: ".json_encode($binds).', result count: '.count($result).", cost time: $costTime sec.");

        return $result;
    }
}
