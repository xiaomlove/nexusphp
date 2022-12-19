<?php
require "../include/bittorrent.php";

$query = \App\Models\Medal::query()->where('get_type', \App\Models\Medal::GET_TYPE_EXCHANGE);
$q = htmlspecialchars($_REQUEST['q'] ?? '');
if (!empty($q)) {
    $query->where('username', 'name', "%{$q}%");
}
$total = (clone $query)->count();
$perPage = 20;
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
$header = '<h1 style="text-align: center">'.$title.'</h1>';
$filterForm = <<<FORM
<div>
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
<td class="colhead">$columnPriceLabel</td>
<td class="colhead">$columnDurationLabel</td>
<td class="colhead">$columnDescriptionLabel</td>
<td class="colhead">$columnActionLabel</td>
</tr>
</thead>
TABLE;
$table .= '<tbody>';
$userMedals = \App\Models\UserMedal::query()->where('uid', $CURUSER['id'])
    ->orderBy('id', 'desc')
    ->get()
    ->keyBy('medal_id')
;
foreach ($rows as $row) {
    if ($userMedals->has($row->id) || $CURUSER['seedbonus'] < $row->price) {
        if ($userMedals->has($row->id)) {
            $btnText = nexus_trans('medal.buy_already');
        } else {
            $btnText = nexus_trans('medal.require_more_bonus');
        }
        $disabled = ' disabled';
        $class = '';
    } else {
        $btnText = nexus_trans('medal.buy_btn');
        $disabled = '';
        $class = 'buy';
    }
    $action = sprintf(
        '<input type="button" class="%s" data-id="%s" value="%s"%s>',
        $class, $row->id, $btnText, $disabled
    );
    $table .= sprintf(
        '<tr><td>%s</td><td>%s</td><td><img src="%s" style="max-width: 60px;max-height: 60px;" class="preview" /></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>',
        $row->id, $row->name, $row->image_large, number_format($row->price), $row->duration, $row->description, $action
    );
}
$table .= '</tbody></table>';
echo $header . $table . $paginationBottom;
end_main_frame();
$confirmMsg = nexus_trans('medal.confirm_to_buy');
$js = <<<JS
jQuery('.buy').on('click', function (e) {
    let medalId = jQuery(this).attr('data-id')
    layer.confirm("{$confirmMsg}", function (index) {
        let params = {
            action: "buyMedal",
            params: {medal_id: medalId}
        }
        console.log(params)
        jQuery.post('ajax.php', params, function(response) {
            console.log(response)
            if (response.ret != 0) {
                layer.alert(response.msg)
                return
            }
            window.location.reload()
        }, 'json')
    })
})
JS;
\Nexus\Nexus::js($js, 'footer', false);
stdfoot();

