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

	$exists = \Nexus\Database\NexusDB::table($table_is)
		->where('userid', (int) $userid)
		->where($field_is, (int) $targetid)
		->exists();
	if ($exists)
	stderr($lang_friends['std_error'], $lang_friends['std_user_id'].$targetid.$lang_friends['std_already_in'].$table_is.$lang_friends['std_list']);

	\Nexus\Database\NexusDB::insert($table_is, [
		'userid' => (int) $userid,
		$field_is => (int) $targetid,
	]);

	purge_neighbors_cache();

	header("Location: " . get_protocol_prefix() . "$BASEURL/friends.php?id=$userid#$frag");
	die;
}

// action: delete ----------------------------------------------------------

if ($action == 'delete')
{
	$targetid = $_GET['targetid'];
	$sure = $_GET['sure'];
	$type = htmlspecialchars($_GET['type']);

	if ($type == 'friend')
	$typename = $lang_friends['text_friend'];
	else $typename = $lang_friends['text_block'];
	if (!is_valid_id($targetid))
	stderr($lang_friends['std_error'], $lang_friends['std_invalid_id']."$userid.");

	if (!$sure) {
        stderr($lang_friends['std_delete'].$type, $lang_friends['std_delete_note'].$typename.$lang_friends['std_click'].
            "<a href=?id=$userid&action=delete&type=$type&targetid=$targetid&sure=1>".$lang_friends['std_here_if_sure'],false);
    }

	if ($type == 'friend')
	{
		$affected = \Nexus\Database\NexusDB::table('friends')
			->where('userid', (int) $userid)
			->where('friendid', (int) $targetid)
			->delete();
		if ($affected == 0)
		stderr($lang_friends['std_error'], $lang_friends['std_no_friend_found']."$targetid");
		$frag = "friends";
	}
	elseif ($type == 'block')
	{
		$affected = \Nexus\Database\NexusDB::table('blocks')
			->where('userid', (int) $userid)
			->where('blockid', (int) $targetid)
			->delete();
		if ($affected == 0)
		stderr($lang_friends['std_error'], $lang_friends['std_no_block_found']."$targetid");
		$frag = "blocks";
	} else {
        stderr($lang_friends['std_error'], $lang_friends['std_unknown_type']."$type");
    }

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
$res = \Nexus\Database\NexusDB::select("SELECT f.friendid as id, u.last_access, u.class, u.avatar, u.title FROM friends AS f LEFT JOIN users as u ON f.friendid = u.id WHERE userid=" . (int) $userid . " ORDER BY id");
if (count($res) == 0)
$friends = $lang_friends['text_friends_empty'];
else
foreach ($res as $friend)
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
//End: Neighbors




$res = \Nexus\Database\NexusDB::select("SELECT blockid as id FROM blocks WHERE userid=" . (int) $userid . " ORDER BY id");
if (count($res) == 0)
$blocks = $lang_friends['text_blocklist_empty'];
else
{
	$i = 0;
	$blocks = "<table width=100% cellspacing=0 cellpadding=0>";
	foreach ($res as $block)
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
if (user_can('viewuserlist'))
	print("<p><a href=users.php><b>".$lang_friends['text_find_user']."</b></a></p>");
stdfoot();
?>
