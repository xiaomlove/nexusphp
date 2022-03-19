<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

$action = $_POST['action'] ?? 'noAction';
$params = $_POST['params'] ?? [];

function noAction()
{
    throw new \RuntimeException("no Action");
}


try {
    $result = call_user_func($action, $params);
    exit(json_encode(success($result)));
} catch (\Throwable $exception) {
    exit(json_encode(fail($exception->getMessage(), $_POST)));
}

function toggleUserMedalStatus($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\MedalRepository();
    return $rep->toggleUserMedalStatus($params['id'], $CURUSER['id']);
}


