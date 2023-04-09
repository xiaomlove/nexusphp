<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
//Send some headers to keep the user's browser from caching the response.
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
header("Cache-Control: no-cache, must-revalidate" );
header("Pragma: no-cache" );
//header("Content-Type: text/xml; charset=utf-8");

$torrentRep = new \App\Repositories\TorrentRepository();
$claimRep = new \App\Repositories\ClaimRepository();
$seedBoxRep = new \App\Repositories\SeedBoxRepository();
$claimTorrentTTL = \App\Models\Claim::getConfigTorrentTTL();
$id = intval($_GET['userid'] ?? 0);
$type = $_GET['type'];
if (!in_array($type,array('uploaded','seeding','leeching','completed','incomplete')))
    die;
if(!user_can('torrenthistory') && $id != $CURUSER["id"])
    permissiondenied();

function maketable($res, $mode = 'seeding')
{
	global $lang_getusertorrentlistajax,$CURUSER,$smalldescription_main, $lang_functions, $id;
	global $torrentRep, $claimRep, $claimTorrentTTL, $seedBoxRep;
	$showActionClaim = $showClient = false;
	switch ($mode)
	{
		case 'uploaded': {
		$showsize = true;
		$showsenum = true;
		$showlenum = true;
		$showuploaded = true;
		$showdownloaded = false;
		$showratio = false;
		$showsetime = true;
		$showletime = false;
		$showcotime = false;
		$showanonymous = true;
        $showtotalsize = true;
		$columncount = 8;
		break;
		}
		case 'seeding': {
		$showsize = true;
		$showsenum = true;
		$showlenum = true;
		$showuploaded = true;
		$showdownloaded = true;
		$showratio = true;
		$showsetime = true;
		$showletime = false;
		$showcotime = false;
		$showanonymous = false;
        $showtotalsize = true;
		$columncount = 8;
            $showActionClaim = true;
            $showClient = true;
		break;
		}
		case 'leeching': {
		$showsize = true;
		$showsenum = true;
		$showlenum = true;
		$showuploaded = true;
		$showdownloaded = true;
		$showratio = true;
		$showsetime = false;
		$showletime = false;
		$showcotime = false;
		$showanonymous = false;
        $showtotalsize = true;
            $showClient = true;
		$columncount = 8;
		break;
		}
		case 'completed': {
		$showsize = true;
		$showsenum = false;
		$showlenum = false;
		$showuploaded = true;
		$showdownloaded = false;
		$showratio = false;
		$showsetime = true;
		$showletime = true;
		$showcotime = true;
		$showanonymous = false;
        $showtotalsize = false;
		$columncount = 8;
            $showActionClaim = true;
		break;
		}
		case 'incomplete': {
		$showsize = true;
		$showsenum = false;
		$showlenum = false;
		$showuploaded = true;
		$showdownloaded = true;
		$showratio = true;
		$showsetime = false;
		$showletime = true;
		$showcotime = false;
		$showanonymous = false;
        $showtotalsize = false;
		$columncount = 7;
		break;
		}
		default: break;
	}
	$shouldShowClient = false;
	if ($showClient && (user_can('userprofile') || $CURUSER['id'] == $id)) {
	    $shouldShowClient = true;
    }
	$results = $torrentIdArr = [];
	while ($row = mysql_fetch_assoc($res)) {
	    $results[] = $row;
	    $torrentIdArr[] = $row['torrent'];
    }
    if ($mode == 'uploaded') {
        //get seedtime, uploaded from snatch
        $seedTimeAndUploaded = \App\Models\Snatch::query()
            ->where('userid', $id)
            ->whereIn('torrentid', $torrentIdArr)
            ->select(['seedtime', 'uploaded', 'torrentid'])
            ->get()
            ->keyBy('torrentid');
    }
    if ($showActionClaim) {
        $claimData = \App\Models\Claim::query()
            ->where('uid', $CURUSER['id'])
            ->whereIn('torrent_id', $torrentIdArr)
            ->get()
            ->keyBy('torrent_id');
    }

	$ret = "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\"><tr><td class=\"colhead\" style=\"padding: 0px\">".$lang_getusertorrentlistajax['col_type']."</td><td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_name']."</td><td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_added']."</td>".
	($showsize ? "<td class=\"colhead\" align=\"center\"><img class=\"size\" src=\"pic/trans.gif\" alt=\"size\" title=\"".$lang_getusertorrentlistajax['title_size']."\" /></td>" : "").($showsenum ? "<td class=\"colhead\" align=\"center\"><img class=\"seeders\" src=\"pic/trans.gif\" alt=\"seeders\" title=\"".$lang_getusertorrentlistajax['title_seeders']."\" /></td>" : "").($showlenum ? "<td class=\"colhead\" align=\"center\"><img class=\"leechers\" src=\"pic/trans.gif\" alt=\"leechers\" title=\"".$lang_getusertorrentlistajax['title_leechers']."\" /></td>" : "").($showuploaded ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_uploaded']."</td>" : "") . ($showdownloaded ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_downloaded']."</td>" : "").($showratio ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_ratio']."</td>" : "").($showsetime ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_se_time']."</td>" : "").($showletime ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_le_time']."</td>" : "").($showcotime ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_time_completed']."</td>" : "").($showanonymous ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_anonymous']."</td>" : "");
    if ($shouldShowClient) {
        $ret .= sprintf('<td class="colhead" align="center">%s</td><td class="colhead" align="center">IP</td>', $lang_getusertorrentlistajax['col_client']);
    }
    $ret .= sprintf('<td class="colhead" align="center">%s</td>', $lang_functions['std_action']);
    $ret .= "</tr>";
    $total_size = 0;
	foreach ($results as $arr)
	{
	    if ($mode == 'uploaded') {
	        $seedTimeAndUploadedData = $seedTimeAndUploaded->get($arr['torrent']);
	        $arr['seedtime'] = $seedTimeAndUploadedData ? $seedTimeAndUploadedData->seedtime : 0;
	        $arr['uploaded'] = $seedTimeAndUploadedData ? $seedTimeAndUploadedData->uploaded : 0;
        }
		$catimage = htmlspecialchars($arr["image"]);
		$catname = htmlspecialchars($arr["catname"]);

		$sphighlight = get_torrent_bg_color($arr['sp_state']);
        $banned_torrent = ($arr["banned"] == 'yes' ? " <b>(<font class=\"striking\">".$lang_functions['text_banned']."</font>)</b>" : "");
		$sp_torrent = get_torrent_promotion_append($arr['sp_state'], '', false, '', 0, '', $arr['__ignore_global_sp_state'] ?? false);
        //Total size
        if ($showtotalsize){
			$total_size += $arr['size'];
		}

		$hrImg = get_hr_img($arr, $arr['search_box_id']);
        $approvalStatusIcon = $torrentRep->renderApprovalStatus($arr["approval_status"]);
		//torrent name
		$dispname = $nametitle = htmlspecialchars($arr["torrentname"]);
		$count_dispname=mb_strlen($dispname,"UTF-8");
		$max_lenght_of_torrent_name=($CURUSER['fontsize'] == 'large' ? 70 : 80);
		if($count_dispname > $max_lenght_of_torrent_name)
			$dispname=mb_substr($dispname, 0, $max_lenght_of_torrent_name,"UTF-8") . "..";
		if ($smalldescription_main == 'yes'){
			//small description
			$dissmall_descr = htmlspecialchars(trim($arr["small_descr"]));
			$count_dissmall_descr=mb_strlen($dissmall_descr,"UTF-8");
			$max_lenght_of_small_descr=80; // maximum length
			if($count_dissmall_descr > $max_lenght_of_small_descr)
			{
				$dissmall_descr=mb_substr($dissmall_descr, 0, $max_lenght_of_small_descr,"UTF-8") . "..";
			}
		}
		else $dissmall_descr == "";
		$ret .= "<tr" .  $sphighlight  . "><td class=\"rowfollow nowrap\" valign=\"middle\" style='padding: 0px'>".return_category_image($arr['category'], "torrents.php?allsec=1&amp;")."</td>\n" .
		"<td class=\"rowfollow\" width=\"100%\" align=\"left\"><a href=\"".htmlspecialchars("details.php?id=".$arr['torrent']."&hit=1")."\" title=\"".$nametitle."\"><b>" . $dispname . "</b></a>". $banned_torrent . $sp_torrent . $hrImg . $approvalStatusIcon .($dissmall_descr == "" ? "" : "<br />" . $dissmall_descr) . "</td>";
		$ret .= sprintf('<td class="rowfollow nowrap" align="center">%s<br/>%s</td>', substr($arr['added'], 0, 10), substr($arr['added'], 11));
		//size
		if ($showsize)
			$ret .= "<td class=\"rowfollow\" align=\"center\">". mksize_compact($arr['size'])."</td>";
		//number of seeders
		if ($showsenum)
			$ret .= "<td class=\"rowfollow\" align=\"center\">".$arr['seeders']."</td>";
		//number of leechers
		if ($showlenum)
			$ret .= "<td class=\"rowfollow\" align=\"center\">".$arr['leechers']."</td>";
		//uploaded amount
		if ($showuploaded){
			$uploaded = mksize_compact($arr["uploaded"]);
			$ret .= "<td class=\"rowfollow\" align=\"center\">".$uploaded."</td>";
		}
		//downloaded amount
		if ($showdownloaded){
			$downloaded = mksize_compact($arr["downloaded"]);
			$ret .= "<td class=\"rowfollow\" align=\"center\">".$downloaded."</td>";
		}
		//ratio
		if ($showratio){
			if ($arr['downloaded'] > 0)
			{
				$ratio = number_format($arr['uploaded'] / $arr['downloaded'], 3);
				$ratio = "<font color=\"" . get_ratio_color($ratio) . "\">".$ratio."</font>";
			}
			elseif ($arr['uploaded'] > 0) $ratio = "Inf.";
			else $ratio = "---";
			$ret .= "<td class=\"rowfollow\" align=\"center\">".$ratio."</td>";
		}
		if ($showsetime){
			$ret .= "<td class=\"rowfollow\" align=\"center\">".mkprettytime($arr['seedtime'])."</td>";
		}
		if ($showletime){
			$ret .= "<td class=\"rowfollow\" align=\"center\">".mkprettytime($arr['leechtime'])."</td>";
		}
		if ($showcotime)
			$ret .= "<td class=\"rowfollow\" align=\"center\">"."". str_replace("&nbsp;", "<br />", gettime($arr['completedat'],false)). "</td>";
		if ($showanonymous)
			$ret .= "<td class=\"rowfollow\" align=\"center\">".$arr['anonymous']."</td>";
		if ($shouldShowClient) {
		    $ipArr = array_filter([$arr['ipv4'], $arr['ipv6']]);
		    foreach ($ipArr as &$_ip) {
		        $_ip = sprintf('<span class="nowrap">%s</span>', $_ip . $seedBoxRep->renderIcon($_ip, $arr['userid']));
            }
		    $ret .= sprintf(
		        '<td class="rowfollow" align="center">%s<br/>%s</td><td class="rowfollow" align="center">%s</td>',
                get_agent($arr['peer_id'], $arr['agent']), $arr['port'],
                implode('<br/>', $ipArr)
            );
        }
        $claimButton = '';
		if (
		    $showActionClaim
            && \App\Models\Claim::getConfigIsEnabled()
            && \Carbon\Carbon::parse($arr['added'])->addDays($claimTorrentTTL)->lte(\Carbon\Carbon::now())
        ) {
            $claim = $claimData->get($arr['torrent']);
		    if ($CURUSER['id'] == $arr['userid']) {
                $claimButton = $claimRep->buildActionButtons($arr['torrent'], $claim);
            } else {
		        if ($claim) {
		            $claimText = nexus_trans('claim.already_claimed');
                } else {
		            $claimText = nexus_trans('claim.not_claim_yet');
                }
                $claimButton = sprintf('<button style="width: max-content;display: flex;align-items: center" disabled>%s</button>', $claimText);
            }
        }
        $ret .= sprintf('<td class="rowfollow" align="center">%s</td>', $claimButton);
		$ret .="</tr>\n";

	}
	$ret .= "</table>\n";
	return [$ret, $total_size];
}
$count = 0;
$torrentlist = "";
switch ($type)
{
	case 'uploaded':
	{
//		$res = sql_query("SELECT torrents.id AS torrent, torrents.name as torrentname, small_descr, seeders, leechers, anonymous, torrents.banned, torrents.approval_status, categories.name AS catname, categories.image, category, sp_state, size, torrents.hr, snatched.seedtime, snatched.uploaded FROM torrents LEFT JOIN snatched ON torrents.id = snatched.torrentid LEFT JOIN categories ON torrents.category = categories.id WHERE torrents.owner=$id AND snatched.userid=$id " . (($CURUSER["id"] != $id)?((get_user_class() < $viewanonymous_class) ? " AND anonymous = 'no'":""):"") ." ORDER BY torrents.added DESC") or sqlerr(__FILE__, __LINE__);
//		$res = sql_query("SELECT torrents.id AS torrent, torrents.name as torrentname, small_descr, seeders, leechers, anonymous, torrents.banned, torrents.approval_status, categories.name AS catname, categories.image, category, sp_state, size, torrents.hr, torrents.added FROM torrents LEFT JOIN categories ON torrents.category = categories.id WHERE torrents.owner=$id " . (($CURUSER["id"] != $id)?((!user_can('viewanonymous')) ? " AND anonymous = 'no'":""):"") ." ORDER BY torrents.id DESC") or sqlerr(__FILE__, __LINE__);
		$fields = "torrents.id AS torrent, torrents.name as torrentname, small_descr, seeders, leechers, anonymous, torrents.banned, torrents.approval_status, categories.name AS catname, categories.image, category, sp_state, size, torrents.hr, torrents.added,torrents.owner as userid, categories.mode as search_box_id";
		$tableWhere = "torrents LEFT JOIN categories ON torrents.category = categories.id WHERE torrents.owner=$id";
		if ($CURUSER['id'] != $id && !user_can('viewanonymous')) {
		    $tableWhere .= " AND anonymous = 'no'";
        }
		$order = "torrents.id DESC";
		break;
	}

	// Current Seeding
	case 'seeding':
	{
//		$res = sql_query("SELECT torrent,added,snatched.uploaded,snatched.downloaded,torrents.name as torrentname, torrents.small_descr, torrents.sp_state, torrents.banned, torrents.approval_status, categories.name as catname,size,torrents.hr,image,category,seeders,leechers FROM peers LEFT JOIN torrents ON peers.torrent = torrents.id LEFT JOIN categories ON torrents.category = categories.id LEFT JOIN snatched ON torrents.id = snatched.torrentid WHERE peers.userid=$id AND snatched.userid = $id AND peers.seeder='yes' ORDER BY torrents.id DESC") or sqlerr();
		$fields = "torrent,added,snatched.uploaded,snatched.downloaded,snatched.seedtime,torrents.name as torrentname, torrents.small_descr, torrents.sp_state, torrents.banned, torrents.approval_status, categories.name as catname,size,torrents.hr,image,category,seeders,leechers,snatched.userid, categories.mode as search_box_id, peers.peer_id, peers.agent, peers.port, peers.ipv4, peers.ipv6";
		$tableWhere = "peers LEFT JOIN torrents ON peers.torrent = torrents.id LEFT JOIN categories ON torrents.category = categories.id LEFT JOIN snatched ON torrents.id = snatched.torrentid WHERE peers.userid=$id AND snatched.userid = $id AND peers.seeder='yes'";
		$order = "torrents.id DESC";
		break;
	}

	// Current Leeching
	case 'leeching':
	{
//		$res = sql_query("SELECT torrent,snatched.uploaded,snatched.downloaded,torrents.name as torrentname, torrents.small_descr, torrents.sp_state, torrents.banned, torrents.approval_status, categories.name as catname,size,torrents.hr,image,category,seeders,leechers, torrents.added FROM peers LEFT JOIN torrents ON peers.torrent = torrents.id LEFT JOIN categories ON torrents.category = categories.id LEFT JOIN snatched ON torrents.id = snatched.torrentid WHERE peers.userid=$id AND snatched.userid = $id AND peers.seeder='no' ORDER BY torrents.id DESC") or sqlerr();
		$fields = "torrent,snatched.uploaded,snatched.downloaded,snatched.seedtime,torrents.name as torrentname, torrents.small_descr, torrents.sp_state, torrents.banned, torrents.approval_status, categories.name as catname,size,torrents.hr,image,category,seeders,leechers, torrents.added,snatched.userid, categories.mode as search_box_id, peers.peer_id, peers.agent, peers.port, peers.ipv4, peers.ipv6";
		$tableWhere = "peers LEFT JOIN torrents ON peers.torrent = torrents.id LEFT JOIN categories ON torrents.category = categories.id LEFT JOIN snatched ON torrents.id = snatched.torrentid WHERE peers.userid=$id AND snatched.userid = $id AND peers.seeder='no'";
        $order = "torrents.id DESC";
		break;
	}

	// Completed torrents
	case 'completed':
	{
//		$res = sql_query("SELECT torrents.id AS torrent, torrents.name AS torrentname, small_descr, categories.name AS catname, torrents.banned, torrents.approval_status, categories.image, category, sp_state, size, torrents.hr, torrents.added,snatched.uploaded, snatched.seedtime, snatched.leechtime, snatched.completedat FROM torrents LEFT JOIN snatched ON torrents.id = snatched.torrentid LEFT JOIN categories on torrents.category = categories.id WHERE snatched.finished='yes' AND torrents.owner != $id AND userid=$id ORDER BY snatched.id DESC") or sqlerr();
		$fields = "torrents.id AS torrent, torrents.name AS torrentname, small_descr, categories.name AS catname, torrents.banned, torrents.approval_status, categories.image, category, sp_state, size, torrents.hr, torrents.added,snatched.uploaded, snatched.seedtime,snatched.uploaded, snatched.leechtime, snatched.completedat,snatched.userid, categories.mode as search_box_id";
		$tableWhere = "torrents LEFT JOIN snatched ON torrents.id = snatched.torrentid LEFT JOIN categories on torrents.category = categories.id WHERE snatched.finished='yes' AND userid=$id AND torrents.owner != $id";
		$order = "snatched.id DESC";
		break;
	}

	// Incomplete torrents
	case 'incomplete':
	{
//		$res = sql_query("SELECT torrents.id AS torrent, torrents.name AS torrentname, small_descr, torrents.banned, torrents.approval_status, categories.name AS catname, categories.image, category, sp_state, size, torrents.hr, torrents.added,snatched.uploaded, snatched.downloaded, snatched.leechtime FROM torrents LEFT JOIN snatched ON torrents.id = snatched.torrentid LEFT JOIN categories on torrents.category = categories.id WHERE snatched.finished='no' AND userid=$id AND torrents.owner != $id ORDER BY snatched.id DESC") or sqlerr();
		$fields = "torrents.id AS torrent, torrents.name AS torrentname, small_descr, torrents.banned, torrents.approval_status, categories.name AS catname, categories.image, category, sp_state, size, torrents.hr, torrents.added,snatched.uploaded, snatched.downloaded, snatched.leechtime,snatched.seedtime,snatched.userid, categories.mode as search_box_id";
		$tableWhere = "torrents LEFT JOIN snatched ON torrents.id = snatched.torrentid LEFT JOIN categories on torrents.category = categories.id WHERE snatched.finished='no' AND userid=$id AND torrents.owner != $id";
		$order = "snatched.id DESC";
		break;
	}
}

if (isset($tableWhere)) {
    $cacheKey = sprintf('user:%s:type:%s:total_size', $id, $type);
    $page = $_GET['page'] ?? 0;
    $sumSql = "select count(*) as count, sum(torrents.size) as total_size from $tableWhere limit 1";
    if ($page == 0) {
        $sumRes = mysql_fetch_assoc(sql_query($sumSql));
        \Nexus\Database\NexusDB::cache_put($cacheKey, $sumRes);
    } else {
        $sumRes = \Nexus\Database\NexusDB::remember($cacheKey, 3600, function () use ($sumSql) {
            return mysql_fetch_assoc(sql_query($sumSql));
        });
    }

    $count = $sumRes['count'];
    $total_size = $sumRes['total_size'];
}

if ($count > 0 && isset($tableWhere, $fields, $order))
{
    $pageSize = 100;
    list($pagertop, $pagerbottom, $limit) = pager($pageSize, $count, "getusertorrentlistajax.php?");
    $sql = "select $fields from $tableWhere order by $order $limit";
    do_log("count: $count, list sql: $sql");
    $res = sql_query($sql);
    list($torrentlist, $total_size_this_page) = maketable ( $res, $type);
}

$table = $pagertop . $torrentlist . $pagerbottom;
$hasData = false;
$summary = sprintf('<b>%s</b>%s', $count, $lang_getusertorrentlistajax['text_record'] . add_s ( $count ));
if (isset($total_size) && $total_size){
    $hasData = true;
    $summary .= $lang_getusertorrentlistajax['text_total_size'] . mksize($total_size);
} elseif ($count) {
    $hasData = true;
}
if ($hasData) {
    $btnArr = apply_filter("user_seeding_top_btn", [], $CURUSER['id']);
    $header = sprintf('<div style="display: flex;justify-content: space-between"><div>%s</div><div>%s</div></div>', $summary, implode("", $btnArr));
    echo '<br/>' . $header . $table;
} else {
    echo $lang_getusertorrentlistajax['text_no_record'];
}

?>
