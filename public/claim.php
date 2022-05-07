<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
$torrentId = $uid = 0;
$actionTh = $actionTd = '';
if (!empty($_GET['torrent_id'])) {
    $torrentId = $_GET['torrent_id'];
    int_check($torrentId,true);
    $torrent = \App\Models\Torrent::query()->where('id', $torrentId)->first(\App\Models\Torrent::$commentFields);
    if (!$torrent) {
        stderr("Error", "Invalid torrent_id: $torrentId");
    }
    stdhead(nexus_trans('claim.title_for_torrent'));
    $query = \App\Models\Claim::query()->where('torrent_id', $torrentId);
    $pagerParam = "?torrent_id=$torrentId";
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
    $pagerParam = "?uid=$uid";
    print("<h1 align=center>".nexus_trans('claim.title_for_user') . "<a href=userdetails.php?id=" . htmlspecialchars($uid) . "><b>&nbsp;".htmlspecialchars($user->username)."</b></a></h1>");
    if ($uid == $CURUSER['id']) {
        $actionTh = sprintf("<td class='colhead' align='center'>%s</td>", nexus_trans("claim.th_action"));
        $actionTd = "<td class='rowfollow nowrap' align='center'><input class='claim-remove' type='button' value='Remove' data-id='%s'></td>";
        $confirmMsg = nexus_trans('claim.confirm_give_up');
        $removeJs = <<<JS
jQuery("#claim-table").on("click", '.claim-remove', function () {
    if (!window.confirm('$confirmMsg')) {
        return
    }
    let params = {action: "removeClaim", params: {id: jQuery(this).attr("data-id")}}
    jQuery.post('ajax.php', params, function (response) {
        console.log(response)
        if (response.ret == 0) {
            location.reload()
        } else {
            window.alert(response.msg)
        }
    }, 'json')
})
JS;
        \Nexus\Nexus::js($removeJs, 'footer', false);
    }
} else {
    stderr("Invalid parameters", "Require torrent_id or uid");
}

begin_main_frame();
$total = (clone $query)->count();
list($pagertop, $pagerbottom, $limit, $offset, $pageSize) = pager(50, $total, "$pagerParam&");
$list = (clone $query)->with(['user', 'torrent', 'snatch'])->offset($offset)->limit($pageSize)->orderBy('id', 'desc')->get();
print("<table id='claim-table' width='100%'>");
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
foreach ($list as $row) {
    if (
        bcsub($row->snatch->seedtime, $row->seed_time_begin) >= $seedTimeRequiredHours * 3600
        || bcsub($row->snatch->uploaded, $row->uploaded_begin) >= $uploadedRequiredTimes * $row->torrent->size
    ) {
        $reached = 'Yes';
    } else {
        $reached = 'No';
    }
    print("<tr>
        <td class='rowfollow nowrap' align='center'>" . $row->id . "</td>
        <td class='rowfollow' align='left'><a href='userdetails.php?id=" . $row->uid . "'>" . $row->user->username . "</a></td>
        <td class='rowfollow' align='left'><a href='details.php?id=" . $row->torrent_id . "'>" . $row->torrent->name . "</a></td>
        <td class='rowfollow nowrap' align='center'>" . mksize($row->torrent->size) . "</td>
        <td class='rowfollow nowrap' align='center'>" . mkprettytime($row->torrent->added->diffInSeconds($now)) . "</td>
        <td class='rowfollow nowrap' align='center'>" . format_datetime($row->created_at) . "</td>
        <td class='rowfollow nowrap' align='center'>" . format_datetime($row->last_settle_at) . "</td>
        <td class='rowfollow nowrap' align='center'>" . mkprettytime($row->snatch->seedtime - $row->seed_time_begin) . "</td>
        <td class='rowfollow nowrap' align='center'>" . mksize($row->snatch->uploaded - $row->uploaded_begin) . "</td>
        <td class='rowfollow nowrap' align='center'>" . $reached . "</td>
        ".sprintf($actionTd, $row->id)."
    </tr>");
}

print("</table>");
print($pagerbottom);
end_main_frame();
stdfoot();


