<?php
namespace Nexus\Database;

class DatabaseException extends \Exception
{

    public function __construct($message, $query = '')
    {
        parent::__construct("$message [$query]");
    }

}