<?php

interface DBInterface
{
    public function connect($host, $username, $password, $database, $port);

    public function query(string $sql);

    public function error(): string;

    public function errno(): int;

    public function numRows($result): int;

    public function selectDb($database);

    public function fetchAssoc($result): array|null;

    public function fetchRow($result): array|null;

    public function affectedRows(): int;

    public function escapeString(string $string): string;

}