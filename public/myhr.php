<?php

require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
require_once(get_langfile_path());
$userid =  $CURUSER['id'];
$pagerParams = [];
if (!empty($_GET['userid'])) {
    if (get_user_class() < $viewhistory_class && $_GET['userid'] != $CURUSER['id']) {
        permissiondenied($viewhistory_class);
    }
    $userid = $_GET['userid'];
    $pagerParams['userid'] = $userid;
}
$userInfo = \App\Models\User::query()->find($userid);
if (empty($userInfo)) {
    stderr('Error', "User not exists.");
}

$pageTitle = $userInfo->username . ' - H&R';
stdhead($pageTitle);
print("<h1>$pageTitle</h1>");

$status = $_GET['status'] ?? \App\Models\HitAndRun::STATUS_INSPECTING;
$allStatus = \App\Models\HitAndRun::listStatus();
$headerFilters = [];
$pagerParams['status'] = $status;
$filterParams = $pagerParams;
$queryString = http_build_query($pagerParams);
foreach ($allStatus as $key => $value) {
    $filterParams['status'] = $key;
    $headerFilters[] = sprintf('<a href="?%s" class="%s"><b>%s</b></a>', http_build_query($filterParams), $key == $status ? 'faqlink' : '', $value['text']);
}

print("<p>" . implode(' | ', $headerFilters) . "</p>");
$q = $_GET['q'] ?? '';
$filterForm = <<<FORM
<form id="filterForm" action="{$_SERVER['REQUEST_URI']}" method="get">
    <input id="q" type="text" name="q" value="{$q}" placeholder="{$lang_myhr['th_hr_id']}">
    <input type="submit">
    <input type="reset" onclick="document.getElementById('q').value='';document.getElementById('filterForm').submit();">
</form>
FORM;

begin_main_frame("", true);

print $filterForm;

$baseQuery = \App\Models\HitAndRun::query()->where('uid', $userid)->where('status', $status);
$rescount = (clone $baseQuery)->count();
list($pagertop, $pagerbottom, $limit, $offset, $pageSize) = pager(50, $rescount, sprintf('?%s&', $queryString));
print("<table width='100%'>");
print("<tr>
				<td class='colhead' align='center'>{$lang_myhr['th_hr_id']}</td>
				<td class='colhead' align='center'>{$lang_myhr['th_torrent_name']}</td>
				<td class='colhead' align='center'>{$lang_myhr['th_uploaded']}</td>
				<td class='colhead' align='center'>{$lang_myhr['th_downloaded']}</td>
				<td class='colhead' align='center'>{$lang_myhr['th_share_ratio']}</td>
				<td class='colhead' align='center'>{$lang_myhr['th_seed_time_required']}</td>
				<td class='colhead' align='center'>{$lang_myhr['th_completed_at']}</td>
				<td class='colhead' align='center'>{$lang_myhr['th_ttl']}</td>
				<td class='colhead' align='center'>{$lang_myhr['th_comment']}</td>
				</tr>");
if ($rescount) {

    $query = (clone $baseQuery)
        ->with([
            'torrent' => function ($query) {$query->select(['id', 'size', 'name']);},
            'snatch',
            'user' => function ($query) {$query->select(['id', 'lang']);},
            'user.language',
        ])
        ->offset($offset)
        ->limit($pageSize)
        ->orderBy('id', 'desc');
    if (!empty($q)) {
        $query->where('id', $q);
    }
    $list = $query->get();

   foreach($list as $row) {
        print("<tr>
				<td class='rowfollow nowrap' align='center'>" . $row->id . "</td>
				<td class='rowfollow' align='left'><a href='details.php?id=" . $row->torrent_id . "'>" . optional($row->torrent)->name . "</a></td>
				<td class='rowfollow nowrap' align='center'>" . mksize($row->snatch->uploaded) . "</td>
				<td class='rowfollow nowrap' align='center'>" . mksize($row->snatch->downloaded) . "</td>
				<td class='rowfollow nowrap' align='center'>" . get_hr_ratio($row->snatch->uploaded, $row->snatch->downloaded) . "</td>
				<td class='rowfollow nowrap' align='center'>" . ($row->status == \App\Models\HitAndRun::STATUS_INSPECTING ? mkprettytime(3600 * get_setting('hr.seed_time_minimum') - $row->snatch->seedtime) : '---') . "</td>
				<td class='rowfollow nowrap' align='center'>" . format_datetime($row->snatch->completedat) . "</td>
				<td class='rowfollow nowrap' align='center' >" . ($row->status == \App\Models\HitAndRun::STATUS_INSPECTING ? mkprettytime(\Carbon\Carbon::now()->diffInSeconds($row->snatch->completedat->addHours(get_setting('hr.inspect_time')))) : '---') . "</td>
                <td class='rowfollow nowrap' align='left' style='padding-left: 10px'>" . nl2br($row->comment) . "</td>
				</tr>");
    }

}


print("</table>");
print($pagerbottom);
end_main_frame();
stdfoot();

