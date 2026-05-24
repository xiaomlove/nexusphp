<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
$sortAllowed = [
    'created_at' => nexus_trans('claim.th_claim_at'),
    'last_settle_at' => nexus_trans('claim.th_last_settle'),
    'seed_time' => nexus_trans('claim.th_seed_time_this_month'),
    'uploaded' => nexus_trans('claim.th_uploaded_this_month'),
];
$orderAllowed = [
    'asc' => nexus_trans('nexus.asc'),
    'desc' => nexus_trans('nexus.desc'),
];
$torrentId = $uid = 0;
$actionTh = $actionTd = '';
$sort = $_GET['sort'] ?? 'created_at';
if (!isset($sortAllowed[$sort])) {
    $sort = "created_at";
}
$order = $_GET['order'] ?? 'asc';
if (!isset($orderAllowed[$order])) {
    $order = "asc";
}
if (!empty($_GET['torrent_id'])) {
    $torrentId = $_GET['torrent_id'];
    int_check($torrentId,true);
    $torrent = \App\Models\Torrent::query()->where('id', $torrentId)->first(\App\Models\Torrent::$commentFields);
    if (!$torrent) {
        stderr("Error", "Invalid torrent_id: $torrentId");
    }
    stdhead(nexus_trans('claim.title_for_torrent'));
    $query = \App\Models\Claim::query()->where('torrent_id', $torrentId);
    $pagerParam = "?torrent_id=$torrentId&sort=$sort&order=$order";
    print("<h1 align=center>".nexus_trans('claim.title_for_torrent') . "<a href=details.php?id=" . htmlspecialchars($torrentId) . "><b>&nbsp;".htmlspecialchars($torrent['name'])."</b></a></h1>");
} elseif (!empty($_GET['uid'])) {
    $uid = $_GET['uid'];
    int_check($uid,true);
    $user = \App\Models\User::query()->where('id', $uid)->first(\App\Models\User::$commonFields);
    if (!$user) {
        stderr("Error", "Invalid uid: $uid");
    }
    stdhead(nexus_trans('claim.title_for_user'));
    $query = \App\Models\Claim::query()->where('uid', $uid);
    $pagerParam = "?uid=$uid&sort=$sort&order=$order";
    print("<h1 align=center>".nexus_trans('claim.title_for_user') . "<a href=userdetails.php?id=" . htmlspecialchars($uid) . "><b>&nbsp;".htmlspecialchars($user->username)."</b></a></h1>");
    if ($uid == $CURUSER['id']) {
        $actionTh = sprintf("<td class='colhead' align='center'>%s</td>", nexus_trans("claim.th_action"));
    }
} else {
    stderr("Invalid parameters", "Require torrent_id or uid");
}

begin_main_frame();
$textSelectOnePlease = nexus_trans('nexus.select_one_please');
$sortOptions = $orderOptions = '';
foreach ($sortAllowed as $name => $text) {
    $sortOptions .= sprintf(
        '<option value="%s"%s>%s</option>',
        $name, isset($_GET['sort']) && $_GET['sort'] == $name ? ' selected' : '', $text
    );
}
foreach ($orderAllowed as $name => $text) {
    $orderOptions .= sprintf(
        '<option value="%s"%s>%s</option>',
        $name, isset($_GET['order']) && $_GET['order'] == $name ? ' selected' : '', $text
    );
}
$resetText = nexus_trans('label.reset');
$submitText = nexus_trans('label.submit');
$sortText = nexus_trans('nexus.sort');
$orderText = nexus_trans('nexus.order');
$filterForm = <<<FORM
<div>
    <form id="filterForm" action="{$_SERVER['REQUEST_URI']}" method="get">
        <input type="hidden" name="uid" value="{$uid}" />
        <input type="hidden" name="torrent_id" value="{$torrentId}" />
        <span>{$sortText}:</span>
        <select name="sort">
            <option value="">-{$textSelectOnePlease}-</option>
            {$sortOptions}
        </select>
        &nbsp;&nbsp;
        <span>{$orderText}:</span>
        <select name="order">
            <option value="">-{$textSelectOnePlease}-</option>
            {$orderOptions}
        </select>
        &nbsp;&nbsp;
        <input type="submit" value="{$submitText}">
        <input type="button" id="reset" value="{$resetText}">
    </form>
</div>
FORM;
$resetJs = <<<JS
jQuery("#reset").on('click', function () {
    jQuery("select[name=sort]").val('')
    jQuery("select[name=order]").val('')
})
JS;
\Nexus\Nexus::js($resetJs, 'footer', false);


$total = (clone $query)->count();
list($pagertop, $pagerbottom, $limit, $offset, $pageSize) = pager(50, $total, "$pagerParam&");
$query = (clone $query)->with(['user', 'torrent', 'snatch'])->offset($offset)->limit($pageSize);
if ($sort == 'seed_time') {
    $query->join("snatched", "claims.snatched_id", "=", "snatched.id")
        ->orderByRaw("(snatched.seedtime - claims.seed_time_begin) $order");
} elseif ($sort == 'uploaded') {
    $query->join("snatched", "claims.snatched_id", "=", "snatched.id")
        ->orderByRaw("(snatched.uploaded - claims.uploaded_begin) $order");
} else {
    $query->orderBy($sort, $order);
}
$list = $query->selectRaw("claims.*")->get();
print($filterForm);
print("<table id='claim-table' width='100%' cellpadding='5'>");
print("<tr>
    <td class='colhead' align='center'>".nexus_trans('claim.th_id')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_username')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_torrent_name')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_torrent_size')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_torrent_ttl')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_claim_at')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_last_settle')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_seed_time_this_month')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_uploaded_this_month')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_reached_or_not')."</td>
    ".$actionTh."
</tr>");
$now = \Carbon\Carbon::now();
$seedTimeRequiredHours = \App\Models\Claim::getConfigStandardSeedTimeHours();
$uploadedRequiredTimes = \App\Models\Claim::getConfigStandardUploadedTimes();
$claimRep = new \App\Repositories\ClaimRepository();
$torrentTool = new \Nexus\Torrent\Torrent();
$torrentIdList = $list->pluck('torrent_id')->toArray();
$leechingSeedingStatus = $torrentTool->listLeechingSeedingStatus($CURUSER['id'], $torrentIdList);
foreach ($list as $row) {
    if (
        bcsub($row->snatch->seedtime, $row->seed_time_begin) >= $seedTimeRequiredHours * 3600
        || bcsub($row->snatch->uploaded, $row->uploaded_begin) >= $uploadedRequiredTimes * $row->torrent->size
    ) {
        $reached = 'Yes';
    } else {
        $reached = 'No';
    }
    $actionTd = '';
    if ($actionTh) {
        $actionTd = sprintf('<td class="rowfollow nowrap" align="center">%s</td>', $claimRep->buildActionButtons($row->torrent_id, $row, 1));
    }
    $torrentName = $row->torrent->name;
    $torrentId = $row->torrent_id;
    if (isset($leechingSeedingStatus[$torrentId])) {
        $torrentName .= $torrentTool->renderProgressBar($leechingSeedingStatus[$torrentId]['active_status'], $leechingSeedingStatus[$torrentId]['progress']);
    }
    print("<tr>
        <td class='rowfollow nowrap' align='center'>" . $row->id . "</td>
        <td class='rowfollow' align='left'><a href='userdetails.php?id=" . $row->uid . "'>" . $row->user->username . "</a></td>
        <td class='rowfollow' align='left'><a href='details.php?id=" . $row->torrent_id . "'>" . $torrentName . "</a></td>
        <td class='rowfollow nowrap' align='center'>" . mksize($row->torrent->size) . "</td>
        <td class='rowfollow nowrap' align='center'>" . mkprettytime($row->torrent->added->diffInSeconds($now, true)) . "</td>
        <td class='rowfollow nowrap' align='center'>" . format_datetime($row->created_at) . "</td>
        <td class='rowfollow nowrap' align='center'>" . format_datetime($row->last_settle_at) . "</td>
        <td class='rowfollow nowrap' align='center'>" . mkprettytime($row->snatch->seedtime - $row->seed_time_begin) . "</td>
        <td class='rowfollow nowrap' align='center'>" . mksize($row->snatch->uploaded - $row->uploaded_begin) . "</td>
        <td class='rowfollow nowrap' align='center'>" . $reached . "</td>
        ".$actionTd."
    </tr>");
}

print("</table>");
print($pagerbottom);
end_main_frame();
stdfoot();


