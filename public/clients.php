<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
if (get_user_class() < UC_SYSOP) {
    permissiondenied();
}

$type = $_GET['type'] ?? 'allow';

$client = new \Nexus\Client\Client($type);


$action = $_GET['action'] ?? 'view';
if ($action == 'view') {
    stdhead($lang_clients['client_management']." - ".$lang_clients['text_field']);
    begin_main_frame();
    $r =  $client->buildClientTable();
    echo $r;
    stdfoot();
} elseif ($action == 'add') {
    stdhead($lang_clients['field_management']." - ".$lang_clients['text_add']);
    begin_main_frame();
    echo $client->buildFieldForm();
} elseif ($action == 'submit') {
    try {
        $result = $client->save($_REQUEST);
        nexus_redirect('clients.php?action=view');
    } catch (\Exception $e) {
        stderr($lang_clients['field_management'], $e->getMessage());
    }
} elseif ($action == 'edit') {
    $id = intval($_GET['id'] ?? 0);
    if ($id == 0) {
        stderr($lang_clients['field_management'], "Invalid id");
    }
    $sql = "select * from torrents_custom_fields where id = $id";
    $res = sql_query($sql);
    $row = mysql_fetch_assoc($res);
    if (empty($row)) {
        stderr('', 'Invalid id');
    }
    stdhead($lang_clients['field_management']." - ".$lang_clients['text_edit']);
    begin_main_frame();
    echo $client->buildFieldForm($row);
} elseif ($action == 'del') {
    $id = intval($_GET['id'] ?? 0);
    if ($id == 0) {
        stderr($lang_clients['field_management'], "Invalid id");
    }
    $sql = "delete from torrents_custom_fields where id = $id";
    $res = sql_query($sql);
    nexus_redirect('clients.php?action=view');
}



