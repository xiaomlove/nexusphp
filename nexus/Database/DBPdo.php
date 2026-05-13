<?php

namespace Nexus\Database;

use PDO;

class DBPdo implements DBInterface
{
    private PDO $pdo;

    private $driver;

    private $lastStmt;

    public function connect($host, $username, $password, $database, $port, $driver = 'mysql')
    {
        $this->driver = $driver;
        if ($driver === 'mysql') {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        } elseif ($driver === 'pgsql') {
            $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        } else {
            throw new DatabaseException('Unsupported driver');
        }

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // 替代 mysqli 报错机制
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // ===== MySQL 专属 =====
        if ($driver === 'mysql') {
            $pdo->exec('SET NAMES utf8mb4');
            $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
            $pdo->exec("SET sql_mode=''");
            $pdo->exec("SET time_zone='".date('P')."'");
        }

        // ===== PostgreSQL 专属 =====
        if ($driver === 'pgsql') {
            $pdo->exec("SET TIME ZONE '".date('P')."'");
        }

        return $this->pdo = $pdo;
    }

    public function query(string $sql)
    {
        $this->lastStmt = $this->pdo->query($sql);

        return $this->lastStmt;
    }

    public function error(): string
    {
        $error = $this->pdo->errorInfo();

        return $error[2] ?? '';
    }

    public function errno(): int
    {
        $error = $this->pdo->errorInfo();

        return (int) ($error[1] ?? 0);
    }

    public function numRows($stmt): int
    {
        // ⚠️ PDO 对 SELECT 不可靠，这里兼容处理
        return $stmt->rowCount();
    }

    public function selectDb($database)
    {
        if ($this->driver === 'mysql') {
            return $this->pdo->exec("USE `{$database}`");
        }

        if ($this->driver === 'pgsql') {
            // PostgreSQL 不能切数据库，只能重连
            throw new DatabaseException('PostgreSQL does not support selectDb()');
        }

        return false;
    }

    public function fetchAssoc($stmt)
    {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchRow($stmt)
    {
        return $stmt->fetch(PDO::FETCH_NUM);
    }

    public function fetchArray($stmt, $type = null)
    {
        if ($type === null) {
            return $stmt->fetch(PDO::FETCH_BOTH);
        }

        // 兼容 mysqli 常量
        switch ($type) {
            case MYSQLI_ASSOC:
                return $stmt->fetch(PDO::FETCH_ASSOC);
            case MYSQLI_NUM:
                return $stmt->fetch(PDO::FETCH_NUM);
            default:
                return $stmt->fetch(PDO::FETCH_BOTH);
        }
    }

    public function affectedRows(): int
    {
        return $this->lastStmt ? $this->lastStmt->rowCount() : 0;
    }

    public function escapeString(string $string): string
    {
        return substr($this->pdo->quote($string), 1, -1);
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function freeResult($stmt)
    {
        return $stmt->closeCursor();
    }

    public function prepare(string $sql): \PDOStatement
    {
        return $this->lastStmt = $this->pdo->prepare($sql);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
