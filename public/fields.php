<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
require_once(get_langfile_path('catmanage.php'));
loggedinorreturn();
if (get_user_class() < UC_ADMINISTRATOR) {
    permissiondenied();
}
$field = new \Nexus\Field\Field();


$action = $_GET['action'] ?? 'view';
if ($action == 'view') {
    stdhead($lang_fields['field_management']." - ".$lang_fields['text_field']);
    begin_main_frame();
    $r =  $field->buildFieldTable();
    echo $r;
    stdfoot();
} elseif ($action == 'add') {
    stdhead($lang_fields['field_management']." - ".$lang_fields['text_add']);
    begin_main_frame();
    echo $field->buildFieldForm();
} elseif ($action == 'submit') {
    try {
        $result = $field->save($_REQUEST);
        nexus_redirect('fields.php?action=view');
    } catch (\Exception $e) {
        stderr($lang_fields['field_management'], $e->getMessage());
    }
} elseif ($action == 'edit') {
    $id = intval($_GET['id'] ?? 0);
    if ($id == 0) {
        stderr($lang_fields['field_management'], "Invalid id");
    }
    $sql = "select * from torrents_custom_fields where id = $id";
    $res = sql_query($sql);
    $row = mysql_fetch_assoc($res);
    if (empty($row)) {
        stderr('', 'Invalid id');
    }
    stdhead($lang_fields['field_management']." - ".$lang_fields['text_edit']);
    begin_main_frame();
    echo $field->buildFieldForm($row);
} elseif ($action == 'del') {
    $id = intval($_GET['id'] ?? 0);
    if ($id == 0) {
        stderr($lang_fields['field_management'], "Invalid id");
    }
    $sql = "delete from torrents_custom_fields where id = $id";
    $res = sql_query($sql);
    nexus_redirect('fields.php?action=view');
}



