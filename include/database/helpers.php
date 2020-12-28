<?php

function mysql_connect($host, $username, $password, $database, $port)
{
    return DB::getInstance()->connect($host, $username, $password, $database, $port);
}

function mysql_errno()
{
    return DB::getInstance()->errno();
}

function mysql_error()
{
    return DB::getInstance()->error();
}

function mysql_query(string $sql)
{
    return DB::getInstance()->query($sql);
}

function mysql_select_db($database)
{
    return DB::getInstance()->select_db($database);
}

function mysql_num_rows($result)
{
    return DB::getInstance()->numRows($result);
}

function mysql_fetch_array($result, $type = null)
{
    return DB::getInstance()->fetchArray($result, $type);
}

function mysql_fetch_assoc($result)
{
    return DB::getInstance()->fetchAssoc($result);
}

function mysql_fetch_row($result)
{
    return DB::getInstance()->fetchRow($result);
}

function mysql_affected_rows()
{
    return DB::getInstance()->affectedRows();
}

function mysql_real_escape_string($string)
{
    return DB::getInstance()->escapeString($string);
}

function mysql_insert_id()
{
    return DB::getInstance()->lastInsertId();
}
