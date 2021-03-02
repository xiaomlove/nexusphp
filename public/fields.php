<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
if (get_user_class() < UC_ADMINISTRATOR) {
    permissiondenied();
}
$field = new \Nexus\Field\Field();

function buildTableHead()
{
    global $lang_fields;
    $head = <<<HEAD
<h1 align="center">{$lang_fields['field_management']} - </h1>
<div>
    <span id="item" onclick="dropmenu(this);">
        <span style="cursor: pointer;" class="big"><b>{$lang_fields['text_manage']}</b></span>
        <div id="itemlist" class="dropmenu" style="display: none">
            <ul>
                <li><a href="?action=view&type=field">{$lang_fields['text_field']}</a></li>
            </ul>
        </div>
    </span>
    <span id="add">
        <a href="?action=add&type=" class="big"><b>{$lang_fields['text_add']}</b></a>
    </span>
</div>
HEAD;
    return $head;
}



$action = $_GET['action'] ?? 'view';
if ($action == 'view') {
    stdhead($lang_fields['field_management']." - ".$lang_fields['text_field']);
    begin_main_frame();
    echo buildTableHead();
    echo $field->buildFieldTable();
} elseif ($action == 'add') {
    stdhead($lang_fields['field_management']." - ".$lang_fields['text_add']);
    begin_main_frame();
    echo $field->buildFieldForm();
} elseif ($action == 'submit') {
    try {
        $result = $field->save($_REQUEST);
        redirect('fields.php?action=view&type=');
    } catch (\Exception $e) {
        stderr($lang_fields['field_management']." - ".$lang_fields['text_field'], $e->getMessage());
    }
} elseif ($action == 'edit') {
    $id = intval($_GET['id'] ?? 0);
    if ($id == 0) {
        stderr($lang_fields['field_management'], "invalid id");
    }
    $sql = "select * from torrents_custom_fields where id = $id";
    $res = sql_query($sql);
    $row = mysql_fetch_assoc($res);
    if (empty($row)) {
        stderr('', 'invlaid id');
    }
    stdhead($lang_fields['field_management']." - ".$lang_fields['text_edit']);
    begin_main_frame();
    echo $field->buildFieldForm($row);
}



