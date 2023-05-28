<?php
namespace Nexus\Database;

interface DBInterface
{
    public function connect($host, $username, $password, $database, $port);

    public function query(string $sql);

    public function error(): string;

    public function errno(): int;

    public function numRows($result): int;

    public function selectDb($database);

    public function fetchAssoc($result);

    public function fetchRow($result);

    public function fetchArray($result, $type);

    public function affectedRows(): int;

    public function escapeString(string $string): string;

    public function lastInsertId(): int;

    public function freeResult($result);

}