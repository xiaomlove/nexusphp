<?php
use Nexus\Database\NexusDB;

function mysql_connect($host, $username, $password, $database, $port)
{
    return NexusDB::getInstance()->connect($host, $username, $password, $database, $port);
}

function mysql_errno()
{
    return NexusDB::getInstance()->errno();
}

function mysql_error()
{
    return NexusDB::getInstance()->error();
}

function mysql_query(string $sql)
{
    return NexusDB::getInstance()->query($sql);
}

function mysql_select_db($database)
{
    return NexusDB::getInstance()->select_db($database);
}

function mysql_num_rows($result)
{
    return NexusDB::getInstance()->numRows($result);
}

function mysql_fetch_array($result, $type = null)
{
    return NexusDB::getInstance()->fetchArray($result, $type);
}

function mysql_fetch_assoc($result)
{
    return NexusDB::getInstance()->fetchAssoc($result);
}

function mysql_fetch_row($result)
{
    return NexusDB::getInstance()->fetchRow($result);
}

function mysql_affected_rows()
{
    return NexusDB::getInstance()->affectedRows();
}

function mysql_real_escape_string($string)
{
    return NexusDB::getInstance()->escapeString($string);
}

function mysql_insert_id()
{
    return NexusDB::getInstance()->lastInsertId();
}

function mysql_free_result($result)
{
    return NexusDB::getInstance()->freeResult($result);
}
