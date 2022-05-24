<?php
require "../include/bittorrent.php";

$query = \App\Models\UserBanLog::query();
$q = $_REQUEST['q'] ?? '';
if (!empty($q)) {
    $query->where('username', 'like', "%{$q}%");
}
$total = (clone $query)->count();
$perPage = 50;
list($paginationTop, $paginationBottom, $limit, $offset) = pager($perPage, $total, "?");
$rows = (clone $query)->offset($offset)->take($perPage)->orderBy('id', 'desc')->get()->toArray();
$header = [
    'id' => 'ID',
    'uid' => 'UID',
    'username' => 'Username',
    'reason' => 'Reason',
    'created_at' => 'Created at',
];
$table = build_table($header, $rows);
$q = htmlspecialchars($q);
$filterForm = <<<FORM
<div>
    <h1 style="text-align: center">User ban log</h1>
    <form id="filterForm" action="{$_SERVER['REQUEST_URI']}" method="get">
        <input id="q" type="text" name="q" value="{$q}" placeholder="username">
        <input type="submit">
        <input type="reset" onclick="document.getElementById('q').value='';document.getElementById('filterForm').submit();">
    </form>
</div>
FORM;
stdhead('User ban log');
begin_main_frame();
echo $filterForm . $table . $paginationBottom;
stdfoot();

