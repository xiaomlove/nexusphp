<?php
require "../include/bittorrent.php";

$query = \App\Models\Medal::query();
$q = htmlspecialchars($_REQUEST['q'] ?? '');
if (!empty($q)) {
    $query->where('username', 'name', "%{$q}%");
}
$total = (clone $query)->count();
$perPage = 50;
list($paginationTop, $paginationBottom, $limit, $offset) = pager($perPage, $total, "?");
$rows = (clone $query)->offset($offset)->take($perPage)->orderBy('id', 'desc')->get();
$q = htmlspecialchars($q);
$title = nexus_trans('medal.label');
$columnNameLabel = nexus_trans('label.name');
$columnImageLargeLabel = nexus_trans('medal.fields.image_large');
$columnPriceLabel = nexus_trans('medal.fields.price');
$columnDurationLabel = nexus_trans('medal.fields.duration');
$columnDescriptionLabel = nexus_trans('medal.fields.description');
$columnActionLabel = nexus_trans('nexus.action');
$filterForm = <<<FORM
<div>
    <h1 style="text-align: center">$title</h1>
    <form id="filterForm" action="{$_SERVER['REQUEST_URI']}" method="get">
        <input id="q" type="text" name="q" value="{$q}" placeholder="username">
        <input type="submit">
        <input type="reset" onclick="document.getElementById('q').value='';document.getElementById('filterForm').submit();">
    </form>
</div>
FORM;
stdhead($title);
begin_main_frame();
$table = <<<TABLE
<table border="1" cellspacing="0" cellpadding="5" width="100%">
<thead>
<tr>
<td class="colhead">ID</td>
<td class="colhead">$columnNameLabel</td>
<td class="colhead">$columnImageLargeLabel</td>
<td class="colhead">$columnDurationLabel</td>
<td class="colhead">$columnDescriptionLabel</td>
<td class="colhead">$columnActionLabel</td>
</tr>
</thead>
TABLE;
$table .= '<tbody>';
$userMedals = \App\Models\UserMedal::query()->where('uid', $CURUSER['id'])->get()->keyBy('medal_id');
foreach ($rows as $row) {
    if ($userMedals->has($CURUSER['id'])) {
        $btnText = nexus_trans('medal.buy_already');
        $disabled = ' disabled';
    } else {
        $btnText = nexus_trans('medal.buy_btn');
        $disabled = '';
    }
    $action = sprintf(
        '<input type="button" value="%s"%s>',
        $btnText, $disabled
    );
    $table .= sprintf(
        '<tr><td>%s</td><td>%s</td><td><img src="%s" style="max-width: 100px" /></td><td>%s</td><td>%s</td><td>%s</td>',
        $row->id, $row->name, $row->image_large, $row->duration, $row->description, $action
    );
}
$table .= '</tbody></table>';
echo $filterForm . $table . $paginationBottom;
stdfoot();

