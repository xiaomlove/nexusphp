<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
//Send some headers to keep the user's browser from caching the response.
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
header("Cache-Control: no-cache, must-revalidate" );
header("Pragma: no-cache" );
header("Content-Type: text/xml; charset=utf-8");

$id = intval($_GET['id'] ?? 0);
$seedBoxRep = new \App\Repositories\SeedBoxRepository();

function get_location_column($e, $isStrongPrivacy, $canView): string
{
    global $enablelocation_tweak, $seedBoxRep, $lang_functions, $lang_viewpeerlist;
    $address = $ips = [];
    if ($enablelocation_tweak == 'yes') {
        if (!empty($e['ipv4'])) {
            list($loc_pub, $loc_mod) = get_ip_location($e['ipv4']);
            $seedBoxIcon = $seedBoxRep->renderIcon($e['ipv4'], $e['userid']);
            $address[] = $loc_pub . $seedBoxIcon;
            $ips[] = $e['ipv4'];
        }
        if (!empty($e['ipv6'])) {
            list($loc_pub, $loc_mod) = get_ip_location($e['ipv6']);
            $seedBoxIcon = $seedBoxRep->renderIcon($e['ipv6'], $e['userid']);
            $address[] = $loc_pub . $seedBoxIcon;
            $ips[] = $e['ipv6'];
        }
        $title = sprintf('%s%s%s', $lang_functions['text_user_ip'], ':&nbsp;', implode(', ', $ips));
        $addressStr = implode('<br/>', $address);
        $location = "<div title='" . $title . "'>" . $addressStr . "</div>";
    } else {
        if (!empty($e['ipv4'])) {
            $ips[] = $e['ipv4'] . $seedBoxRep->renderIcon($e['ipv4'], $e['userid']);
        }
        if (!empty($e['ipv6'])) {
            $ips[] = $e['ipv6'] . $seedBoxRep->renderIcon($e['ipv6'], $e['userid']);
        }
        $location = '<div>'.implode('<br/>', $ips).'</div>';
    }

    if ($isStrongPrivacy) {
        $result = '<div style="margin-right: 6px"><i>'.$lang_viewpeerlist['text_anonymous'].'</i></div>';
        if ($canView) {
            $result .= $location;
        }
    } else {
        $result = $location;
    }

    return "<td class=rowfollow align=left width=1%><div style='display: flex;white-space: nowrap;align-items: center'>" . $result . "</div></td>\n";
}

function get_username_seed_box_icon($e): string
{
    global $seedBoxRep;
    foreach (array_filter([$e['ipv4'], $e['ipv6']]) as $ip) {
        $icon = $seedBoxRep->renderIcon($ip, $e['userid']);
        if (!empty($icon)) {
            return $icon;
        }
    }
    return '';
}


if(isset($CURUSER))
{
function dltable($name, $arr, $torrent)
{
	global $lang_viewpeerlist,$viewanonymous_class,$userprofile_class,$enablelocation_tweak;
	global $CURUSER;
	global $lang_functions, $seedBoxRep;

	$s = "<b>" . count($arr) . " $name</b>\n";
	$showLocationColumn = $enablelocation_tweak == 'yes' || get_user_class() >= $userprofile_class;
	if (!count($arr))
		return $s;
	$s .= "\n";
	$s .= "<table width=100% class=main border=1 cellspacing=0 cellpadding=3>\n";
	$s .= "<tr><td class=colhead align=center width=1%>".$lang_viewpeerlist['col_user_ip']."</td>" .
	($showLocationColumn ? "<td class=colhead align=center>".$lang_viewpeerlist['col_location']."</td>" : "").
	"<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_connectable']."</td>".
	"<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_uploaded']."</td>".
	"<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_rate']."</td>" .
	"<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_downloaded']."</td>" .
	"<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_rate']."</td>" .
	"<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_ratio']."</td>" .
	"<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_complete']."</td>" .
	"<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_connected']."</td>" .
	"<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_idle']."</td>" .
	"<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_client']."</td></tr>\n";
	$now = time();
	$num = 0;
    $privacyData = \App\Models\User::query()->whereIn('id', array_column($arr, 'userid'))->get(['id', 'privacy'])->keyBy('id');
	foreach ($arr as $e) {
        $privacy = $privacyData->get($e['userid'])->privacy ?? '';
		++$num;

		$highlight = $CURUSER["id"] == $e['userid'] ? " bgcolor=#BBAF9B" : "";
		$s .= "<tr$highlight>\n";
        $secs = max(1, ($e["la"] - $e["st"]));
        $columnLocation = $usernameSeedBoxIcon = '';
		if ($privacy == "strong" || ($torrent['anonymous'] == 'yes' && $e['userid'] == $torrent['owner'])) {
			if (get_user_class() >= $viewanonymous_class || $e['userid'] == $CURUSER['id']) {
                if ($showLocationColumn) {
                    $columnLocation = get_location_column($e, true, true);
                } else {
                    $usernameSeedBoxIcon = get_username_seed_box_icon($e);
                }
                $columnUsername = "<td class=rowfollow align=left width=1%><i>".$lang_viewpeerlist['text_anonymous']."</i>".$usernameSeedBoxIcon."<br />(" . get_username($e['userid']) . ")</td>\n";
            } else {
                if ($showLocationColumn) {
                    $columnLocation = get_location_column($e, true, false);
                } else {
                    $usernameSeedBoxIcon = get_username_seed_box_icon($e);
                }
                $columnUsername = "<td class=rowfollow align=left width=1%><i>".$lang_viewpeerlist['text_anonymous']."</i>".$usernameSeedBoxIcon."</td>\n";
            }
		} else {
            if ($showLocationColumn) {
                $columnLocation = get_location_column($e, false, false);
            } else {
                $usernameSeedBoxIcon = get_username_seed_box_icon($e);
            }
            $columnUsername = "<td class=rowfollow align=left width=1%>" . get_username($e['userid']).$usernameSeedBoxIcon."</td>\n";
        }
		$s .= $columnUsername . $columnLocation;

		$s .= "<td class=rowfollow align=center width=1%><nobr>" . ($e['connectable'] == "yes" ? $lang_viewpeerlist['text_yes'] : "<font color=red>".$lang_viewpeerlist['text_no']."</font>") . "</nobr></td>\n";
		$s .= "<td class=rowfollow align=center width=1%><nobr>" . mksize($e["uploaded"]) . "</nobr></td>\n";

		$s .= "<td class=rowfollow align=center width=1%><nobr>" . mksize(($e["uploaded"] - $e["uploadoffset"]) / $secs) . "/s</nobr></td>\n";
		$s .= "<td class=rowfollow align=center width=1%><nobr>" . mksize($e["downloaded"]) . "</nobr></td>\n";

		if ($e["seeder"] == "no")
		$s .= "<td class=rowfollow align=center width=1%><nobr>" . mksize(($e["downloaded"] - $e["downloadoffset"]) / $secs) . "/s</nobr></td>\n";
		else
		$s .= "<td class=rowfollow align=center width=1%><nobr>" . mksize(($e["downloaded"] - $e["downloadoffset"]) / max(1, $e["finishedat"] - $e['st'])) .	"/s</nobr></td>\n";
		if ($e["downloaded"])
		{
			$ratio = floor(($e["uploaded"] / $e["downloaded"]) * 1000) / 1000;
			$s .= "<td class=rowfollow align=\"center\" width=1%><font color=" . get_ratio_color($ratio) . "><nobr>" . number_format($ratio, 3) . "</nobr></font></td>\n";
		}
		elseif ($e["uploaded"])
		$s .= "<td class=rowfollow align=center width=1%>".$lang_viewpeerlist['text_inf']."</td>\n";
		else
		$s .= "<td class=rowfollow align=center width=1%>---</td>\n";
		$s .= "<td class=rowfollow align=center width=1%><nobr>" . sprintf("%.2f%%", 100 * (1 - ($e["to_go"] / $torrent["size"]))) . "</nobr></td>\n";
		$s .= "<td class=rowfollow align=center width=1%><nobr>" . mkprettytime($now - $e["st"]) . "</nobr></td>\n";
		$s .= "<td class=rowfollow align=center width=1%><nobr>" . mkprettytime($now - $e["la"]) . "</nobr></td>\n";
		$s .= "<td class=rowfollow align=center width=1%><nobr>" . htmlspecialchars(get_agent($e["peer_id"],$e["agent"])) . "</nobr></td>\n";
		$s .= "</tr>\n";
	}
	$s .= "</table>\n";
	return $s;
}
	$downloaders = array();
	$seeders = array();
	$torrent = \App\Models\Torrent::query()->findOrFail($id, ['id', 'seeders', 'leechers']);
	$subres = sql_query("SELECT seeder, finishedat, downloadoffset, uploadoffset, ip, ipv4, ipv6, port, uploaded, downloaded, to_go, UNIX_TIMESTAMP(started) AS st, connectable, agent, peer_id, UNIX_TIMESTAMP(last_action) AS la, userid FROM peers WHERE torrent = $id") or sqlerr();
	while ($subrow = mysql_fetch_array($subres)) {
	if ($subrow["seeder"] == "yes")
		$seeders[] = $subrow;
	else
		$downloaders[] = $subrow;
	}
	$seedersCount = count($seeders);
	$leechersCount = count($downloaders);
    if ($torrent->seeders != $seedersCount || $torrent->leechers != $leechersCount) {
        $update = [
            'seeders' => $seedersCount,
            'leechers' => $leechersCount,
        ];
        $torrent->update($update);
        do_log("[UPDATE_TORRENT_SEEDERS_LEECHERS], torrent: $id, original: " . $torrent->toJson() . ", update: " . json_encode($update));
    }
	function leech_sort($a,$b) {
		$x = $a["to_go"];
		$y = $b["to_go"];
		if ($x == $y)
			return 0;
		if ($x < $y)
			return -1;
		return 1;
	}
	function seed_sort($a,$b) {
		$x = $a["uploaded"];
		$y = $b["uploaded"];
		if ($x == $y)
			return 0;
		if ($x < $y)
			return 1;
		return -1;
	}
	$res = sql_query("SELECT torrents.id, torrents.owner, torrents.size, torrents.anonymous FROM torrents WHERE torrents.id = $id LIMIT 1") or sqlerr();
	$row = mysql_fetch_array($res);
	usort($seeders, "seed_sort");
	usort($downloaders, "leech_sort");

	print(dltable($lang_viewpeerlist['text_seeders'], $seeders, $row));
	print(dltable($lang_viewpeerlist['text_leechers'], $downloaders, $row));
}
?>
