<?php

class DatabaseException extends \Exception
{

    public function __construct($query, $message)
    {
        parent::__construct("$message [$query]");
    }

}