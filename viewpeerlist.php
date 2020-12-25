<?php
require "include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
//Send some headers to keep the user's browser from caching the response.
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); 
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" ); 
header("Cache-Control: no-cache, must-revalidate" ); 
header("Pragma: no-cache" );
header("Content-Type: text/xml; charset=utf-8");

$id = 0 + $_GET['id'];
if(isset($CURUSER))
{
function dltable($name, $arr, $torrent)
{
	global $lang_viewpeerlist,$viewanonymous_class,$userprofile_class,$enablelocation_tweak;
	global $CURUSER;
	$s = "<b>" . count($arr) . " $name</b>\n";
	if (!count($arr))
		return $s;
	$s .= "\n";
	$s .= "<table width=825 class=main border=1 cellspacing=0 cellpadding=3>\n";
	$s .= "<tr><td class=colhead align=center width=1%>".$lang_viewpeerlist['col_user_ip']."</td>" .
	($enablelocation_tweak == 'yes' || get_user_class() >= $userprofile_class ? "<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_location']."</td>" : "").
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
	foreach ($arr as $e) {
		$privacy = get_single_value("users", "privacy","WHERE id=".sqlesc($e['userid']));
		++$num;

		$highlight = $CURUSER["id"] == $e['userid'] ? " bgcolor=#BBAF9B" : "";
		$s .= "<tr$highlight>\n";
		if($privacy == "strong" || ($torrent['anonymous'] == 'yes' && $e['userid'] == $torrent['owner']))
		{
			if(get_user_class() >= $viewanonymous_class || $e['userid'] == $CURUSER['id'])
				$s .= "<td class=rowfollow align=left width=1%><i>".$lang_viewpeerlist['text_anonymous']."</i><br />(" . get_username($e['userid']) . ")";
			else
				$s .= "<td class=rowfollow align=left width=1%><i>".$lang_viewpeerlist['text_anonymous']."</i></a></td>\n";
		}
		else
			$s .= "<td class=rowfollow align=left width=1%>" . get_username($e['userid']);

		$secs = max(1, ($e["la"] - $e["st"]));
		if ($enablelocation_tweak == 'yes'){
			list($loc_pub, $loc_mod) = get_ip_location($e["ip"]);
			$location = get_user_class() >= $userprofile_class ? "<div title='" . $loc_mod . "'>" . $loc_pub . "</div>" : $loc_pub;
			$s .= "<td class=rowfollow align=center width=1%><nobr>" . $location . "</nobr></td>\n";
		}
		elseif (get_user_class() >= $userprofile_class){
			$location = $e["ip"];
			$s .= "<td class=rowfollow align=center width=1%><nobr>" . $location . "</nobr></td>\n";
		}
		else $location = "";

		$s .= "<td class=rowfollow align=center width=1%><nobr>" . ($e[connectable] == "yes" ? $lang_viewpeerlist['text_yes'] : "<font color=red>".$lang_viewpeerlist['text_no']."</font>") . "</nobr></td>\n";
		$s .= "<td class=rowfollow align=center width=1%><nobr>" . mksize($e["uploaded"]) . "</nobr></td>\n";

		$s .= "<td class=rowfollow align=center width=1%><nobr>" . mksize(($e["uploaded"] - $e["uploadoffset"]) / $secs) . "/s</nobr></td>\n";
		$s .= "<td class=rowfollow align=center width=1%><nobr>" . mksize($e["downloaded"]) . "</nobr></td>\n";

		if ($e["seeder"] == "no")
		$s .= "<td class=rowfollow align=center width=1%><nobr>" . mksize(($e["downloaded"] - $e["downloadoffset"]) / $secs) . "/s</nobr></td>\n";
		else
		$s .= "<td class=rowfollow align=center width=1%><nobr>" . mksize(($e["downloaded"] - $e["downloadoffset"]) / max(1, $e["finishedat"] - $e[st])) .	"/s</nobr></td>\n";
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
	$subres = sql_query("SELECT seeder, finishedat, downloadoffset, uploadoffset, ip, port, uploaded, downloaded, to_go, UNIX_TIMESTAMP(started) AS st, connectable, agent, peer_id, UNIX_TIMESTAMP(last_action) AS la, userid FROM peers WHERE torrent = $id") or sqlerr();
	while ($subrow = mysql_fetch_array($subres)) {
	if ($subrow["seeder"] == "yes")
		$seeders[] = $subrow;
	else
		$downloaders[] = $subrow;
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
