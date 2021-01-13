<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();


function purge_neighbors_cache()
{
	global $CURUSER;
	$cachefile = "cache/" . get_langfolder_cookie() . "/neighbors/" . $CURUSER['id'] . ".html";
	if (file_exists($cachefile))
		unlink($cachefile);
}

//make_folder("cache/" , get_langfolder_cookie());
//make_folder("cache/" , get_langfolder_cookie() . "/neighbors");

$userid = $CURUSER['id'];
$action = $_GET['action'] ?? '';

if (!is_valid_id($userid))
stderr($lang_friends['std_error'], $lang_friends['std_invalid_id']."$userid.");

$user = $CURUSER;
// action: add -------------------------------------------------------------

if ($action == 'add')
{
	$targetid = $_GET['targetid'];
	$type = $_GET['type'];

	if (!is_valid_id($targetid))
	stderr($lang_friends['std_error'], $lang_friends['std_invalid_id']."$targetid.");

	if ($type == 'friend')
	{
		$table_is = $frag = 'friends';
		$field_is = 'friendid';
	}
	elseif ($type == 'block')
	{
		$table_is = $frag = 'blocks';
		$field_is = 'blockid';
	}
	else
	stderr($lang_friends['std_error'], $lang_friends['std_unknown_type']."$type");

	$r = sql_query("SELECT id FROM $table_is WHERE userid=$userid AND $field_is=$targetid") or sqlerr(__FILE__, __LINE__);
	if (mysql_num_rows($r) == 1)
	stderr($lang_friends['std_error'], $lang_friends['std_user_id'].$targetid.$lang_friends['std_already_in'].$table_is.$lang_friends['std_list']);

	sql_query("INSERT INTO $table_is VALUES (0,$userid, $targetid)") or sqlerr(__FILE__, __LINE__);
	
	purge_neighbors_cache();
	
	header("Location: " . get_protocol_prefix() . "$BASEURL/friends.php?id=$userid#$frag");
	die;
}

// action: delete ----------------------------------------------------------

if ($action == 'delete')
{
	$targetid = $_GET['targetid'];
	$sure = $_GET['sure'];
	$type = $_GET['type'];

	if ($type == 'friend')
	$typename = $lang_friends['text_friend'];
	else $typename = $lang_friends['text_block'];
	if (!is_valid_id($targetid))
	stderr($lang_friends['std_error'], $lang_friends['std_invalid_id']."$userid.");

	if (!$sure)
	stderr($lang_friends['std_delete'].$type, $lang_friends['std_delete_note'].$typename.$lang_friends['std_click'].
	"<a href=?id=$userid&action=delete&type=$type&targetid=$targetid&sure=1>".$lang_friends['std_here_if_sure'],false);

	if ($type == 'friend')
	{
		sql_query("DELETE FROM friends WHERE userid=$userid AND friendid=$targetid") or sqlerr(__FILE__, __LINE__);
		if (mysql_affected_rows() == 0)
		stderr($lang_friends['std_error'], $lang_friends['std_no_friend_found']."$targetid");
		$frag = "friends";
	}
	elseif ($type == 'block')
	{
		sql_query("DELETE FROM blocks WHERE userid=$userid AND blockid=$targetid") or sqlerr(__FILE__, __LINE__);
		if (mysql_affected_rows() == 0)
		stderr($lang_friends['std_error'], $lang_friends['std_no_block_found']."$targetid");
		$frag = "blocks";
	}
	else
	stderr($lang_friends['std_error'], $lang_friends['std_unknown_type']."$type");


	purge_neighbors_cache();

	header("Location: " . get_protocol_prefix() . "$BASEURL/friends.php?id=$userid#$frag");
	die;
}

// main body  -----------------------------------------------------------------

stdhead($lang_friends['head_personal_lists_for']. $user['username']);

print("<p><table class=main border=0 cellspacing=0 cellpadding=0>".
"<tr><td class=embedded><h1 style='margin:0px'> " . $lang_friends['text_personallist'] . " ".get_username($user['id'])."</h1></td></tr></table></p>\n");

//Start: Friends
print("<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>");

print("<br />");
print("<h2 align=left><a name=\"friends\">" . $lang_friends['text_friendlist'] . "</a></h2>\n");

print("<table width=737 border=1 cellspacing=0 cellpadding=5><tr class=tablea><td>");

$i = 0;

unset($friend_id_arr);
$res = sql_query("SELECT f.friendid as id, u.last_access, u.class, u.avatar, u.title FROM friends AS f LEFT JOIN users as u ON f.friendid = u.id WHERE userid=$userid ORDER BY id") or sqlerr(__FILE__, __LINE__);
if(mysql_num_rows($res) == 0)
$friends = $lang_friends['text_friends_empty'];
else
while ($friend = mysql_fetch_array($res))
{
	$friend_id_arr[] = $friend["id"];
	$title = $friend["title"];
	if (!$title)
		$title = get_user_class_name($friend["class"],false,true,true);
	$body1 = get_username($friend["id"]) .
	" ($title)<br /><br />".$lang_friends['text_last_seen_on']. gettime($friend['last_access'],true, false);
	$body2 = "<a href=friends.php?id=$userid&action=delete&type=friend&targetid=" . $friend['id'] . ">".$lang_friends['text_remove_from_friends']."</a>".
	"<br /><br /><a href=sendmessage.php?receiver=" . $friend['id'] . ">".$lang_friends['text_send_pm']."</a>";

	$avatar = ($CURUSER["avatars"] == "yes" ? htmlspecialchars($friend["avatar"]) : "");
	if (!$avatar)
	$avatar = "pic/default_avatar.png";
	if ($i % 2 == 0)
	print("<table width=100% style='padding: 0px'><tr><td class=bottom style='padding: 5px' width=50% align=center>");
	else
	print("<td class=bottom style='padding: 5px' width=50% align=center class=tablea>");
	print("<table class=main width=100% height=75px class=tablea>");
	print("<tr valign=top class=tableb><td width=75 align=center style='padding: 0px'>" .
	($avatar ? "<div style='width:75px;height:75px;overflow: hidden'><img width=75px src=\"$avatar\"></div>" : ""). "</td><td>\n");
	print("<table class=main>");
	print("<tr><td class=embedded style='padding: 5px' width=80%>$body1</td>\n");
	print("<td class=embedded style='padding: 5px' width=20%>$body2</td></tr>\n");
	print("</table>");
	print("</td></tr>");
	print("</td></tr></table>\n");
	if ($i % 2 == 1)
	print("</td></tr></table>\n");
	else
	print("</td>\n");
	$i++;
}
if ($i % 2 == 1)
print("<td class=bottom width=50%>&nbsp;</td></tr></table>\n");
print($friends);
print("</td></tr></table><br />\n");
//End: Friends

//Start: Neighbors
/*
print("<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>");

print("<br />");
print("<h2 align=left><a name=\"friendsadded\">".$lang_friends['text_neighbors']."</a></h2>\n");

print("<table width=737 border=1 cellspacing=0 cellpadding=5><tr class=tablea><td>");

$i = 0;
$cachefile = "cache/" . get_langfolder_cookie() . "/neighbors/" . $CURUSER['id'] . ".html";
$cachetime = 24 * 60 * 60; // 1 day
if (file_exists($cachefile) && (time() - $cachetime< filemtime($cachefile)))
{
	include($cachefile);
}
else
{
	ob_start(); // start the output buffer

	$user_snatched = sql_query("SELECT * FROM snatched WHERE userid = $CURUSER[id]") or sqlerr(__FILE__, __LINE__);
	if(mysql_num_rows($user_snatched) == 0)
	$neighbors_info = $lang_friends['text_neighbors_empty'];
	else
	{
		while ($user_snatched_arr = mysql_fetch_array($user_snatched))
		{
			$torrent_2_user_value = get_torrent_2_user_value($user_snatched_arr);

			$user_snatched_res_target = sql_query("SELECT * FROM snatched WHERE torrentid = " . $user_snatched_arr['torrentid'] . " AND userid != " . $user_snatched_arr['userid']) or sqlerr(__FILE__, __LINE__);	//
			if(mysql_num_rows($user_snatched_res_target)>0)	// have other peole snatched this torrent
			{
				while($user_snatched_arr_target = mysql_fetch_array($user_snatched_res_target))	// find target user's current analyzing torrent's snatch info
				{
					$torrent_2_user_value_target = get_torrent_2_user_value($user_snatched_arr_target);	//get this torrent to target user's value

					if(!isset($other_user_2_curuser_value[$user_snatched_arr_target['userid']]))	// first, set to 0
					$other_user_2_curuser_value[$user_snatched_arr_target['userid']] = 0.0;

					$other_user_2_curuser_value[$user_snatched_arr_target['userid']] += $torrent_2_user_value_target * $torrent_2_user_value;
				}
			}
		}

		arsort($other_user_2_curuser_value,SORT_NUMERIC);
		$counter = 0;
		$total_user = count($other_user_2_curuser_value);
		while(1)
		{
			list($other_user_2_curuser_value_key, $other_user_2_curuser_value_val) = each($other_user_2_curuser_value);
			//print(" userid: " . $other_user_2_curuser_value_key . " value: " . $other_user_2_curuser_value_val . "<br />");


			$neighbors_res = sql_query("SELECT * FROM users WHERE id = " . intval($other_user_2_curuser_value_key)) or sqlerr(__FILE__, __LINE__);
			if(mysql_num_rows($neighbors_res) == 1)
			{
				$neighbors_arr = mysql_fetch_array($neighbors_res) or sqlerr(__FILE__, __LINE__);
				if($neighbors_arr['enabled'] == 'yes')
				{
					$title = $neighbors_arr["title"];
					if (!$title)
					$title = get_user_class_name($neighbors_arr["class"],false,true,true);
					$body1 = get_username($neighbors_arr["id"]) .
					" ($title)<br /><br />".$lang_friends['text_last_seen_on']. gettime($neighbors_arr['last_access'], true, false);

					
					$body2 = ((empty($friend_id_arr)||(!in_array($neighbors_arr["id"],$friend_id_arr))) ? "<a href=friends.php?id=$userid&action=add&type=friend&targetid=" . $neighbors_arr['id'] . ">".$lang_friends['text_add_to_friends']."</a>" : "<a href=friends.php?id=$userid&action=delete&type=friend&targetid=" . $neighbors_arr['id'] . ">".$lang_friends['text_remove_from_friends']."</a>") .
					"<br /><br /><a href=sendmessage.php?receiver=" . $neighbors_arr['id'] . ">".$lang_friends['text_send_pm']."</a>";
					$avatar = ($CURUSER["avatars"] == "yes" ? htmlspecialchars($neighbors_arr["avatar"]) : "");
					if (!$avatar)
					$avatar = "pic/default_avatar.png";
					if ($i % 2 == 0)
					print("<table width=100% style='padding: 0px'><tr><td class=bottom style='padding: 5px' width=50% align=center>");
					else
					print("<td class=bottom style='padding: 5px' width=50% align=center>");
					print("<table class=main width=100% height=75px>");
					print("<tr valign=top><td width=75 align=center style='padding: 0px'>" .
					($avatar ? "<div style='width:75px;height:75px;overflow: hidden'><img width=75px src=\"$avatar\"></div>" : ""). "</td><td>\n");
					print("<table class=main>");
					print("<tr><td class=embedded style='padding: 5px' width=80%>$body1</td>\n");
					print("<td class=embedded style='padding: 5px' width=20%>$body2</td></tr>\n");
					print("</table>");
					print("</td></tr>");
					print("</td></tr></table>\n");
					if ($i % 2 == 1)
					print("</td></tr></table>\n");
					else
					print("</td>\n");
					$i++;
					$counter++;
				}
			}
			$total_user--;
			if($counter == 20 || $total_user<=0)	break;	//only the largest 20
		}
	}
	if ($i % 2 == 1)
	print("<td class=bottom width=50%>&nbsp;</td></tr></table>\n");
	print($neighbors_info);
	print("</td></tr></table></table><br />\n");

	// CACHE END //////////////////////////////////////////////////

	// open the cache file for writing
	$fp = fopen($cachefile, 'w');
	// save the contents of output buffer to the file
	fwrite($fp, ob_get_contents());
	// close the file
	fclose($fp);
	// Send the output to the browser
	ob_end_flush();

	/////////////////////////////////////////////////////////
}


if(mysql_num_rows($friendadd) == 0)
$friendsno = $lang_friends['text_friends_empty'];
else
while ($friend = mysql_fetch_array($friendadd))
{
$title = $friend["title"];
if (!$title)
$title = get_user_class_name($friend["class"],false,true,true);
$body1 = get_username($friend["fuid"]) .
" ($title)<br /><br />".$lang_friends['text_last_seen_on']. $friend['last_access'] .
"<br />(" . get_elapsed_time(strtotime($friend[last_access])) . $lang_friends['text_ago'].")";
$body2 = "<a href=friends.php?id=$userid&action=add&type=friend&targetid=" . $friend['fuid'] . ">".$lang_friends['text_add_to_friends']."</a>".
"<br /><br /><a href=sendmessage.php?receiver=" . $friend['fuid'] . ">".$lang_friends['text_send_pm']."</a>";
$avatar = ($CURUSER["avatars"] == "yes" ? htmlspecialchars($friend["avatar"]) : "");
if (!$avatar)
$avatar = "pic/default_avatar.png";
if ($i % 2 == 0)
print("<table width=100% style='padding: 0px'><tr><td class=bottom style='padding: 5px' width=50% align=center>");
else
print("<td class=bottom style='padding: 5px' width=50% align=center class=tablea>");
print("<table class=main width=100% height=75px class=tablea>");
print("<tr valign=top class=tableb><td width=75 align=center style='padding: 0px'>" .
($avatar ? "<div style='width:75px;height:75px;overflow: hidden'><img width=75px src=\"$avatar\"></div>" : ""). "</td><td>\n");
print("<table class=main>");
print("<tr><td class=embedded style='padding: 5px' width=80%>$body1</td>\n");
print("<td class=embedded style='padding: 5px' width=20%>$body2</td></tr>\n");
print("</table>");
print("</td></tr>");
print("</td></tr></table>\n");
if ($i % 2 == 1)
print("</td></tr></table>\n");
else
print("</td>\n");
$i++;
}
if ($i % 2 == 1)
print("<td class=bottom width=50%>&nbsp;</td></tr></table>\n");
print($neighbors_info);
print("</td></tr></table></table><br />\n");
*/
//End: Neighbors




$res = sql_query("SELECT blockid as id FROM blocks WHERE userid=$userid ORDER BY id") or sqlerr(__FILE__, __LINE__);
if(mysql_num_rows($res) == 0)
$blocks = $lang_friends['text_blocklist_empty'];
else
{
	$i = 0;
	$blocks = "<table width=100% cellspacing=0 cellpadding=0>";
	while ($block = mysql_fetch_array($res))
	{
		if ($i % 6 == 0)
		$blocks .= "<tr>";
		$blocks .= "<td style='border: none; padding: 4px; spacing: 0px;'>[<font class=small><a href=friends.php?id=$userid&action=delete&type=block&targetid=" .
		$block['id'] . ">D</a></font>] " . get_username($block["id"]) . "</td>";
		if ($i % 6 == 5)
		$blocks .= "</tr>";
		$i++;
	}
	$blocks .= "</table>\n";
}
print("<br /><br />");
print("<table class=main width=737 border=0 cellspacing=0 cellpadding=5><tr><td class=embedded>");
print("<h2 align=left><a name=\"blocks\">".$lang_friends['text_blocked_users']."</a></h2></td></tr>");
print("<tr class=tableb><td style='padding: 10px;'>");
print($blocks);
print("</td></tr></table>\n");

print("</td></tr></table>\n");
if (get_user_class() >= $viewuserlist_class)
	print("<p><a href=users.php><b>".$lang_friends['text_find_user']."</b></a></p>");
stdfoot();
?>
