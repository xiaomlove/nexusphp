<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();

$reportofferid = $_GET["reportofferid"];
$reportrequestid = $_GET["reportrequestid"];
$user = $_GET["user"];
$commentid = $_GET["commentid"];
$torrent = $_GET["torrent"];
$forumpost = $_GET["forumpost"];
$subtitle = $_GET["subtitle"];

$takeuser = $_POST["takeuser"];
$takecommentid = $_POST["takecommentid"];
$taketorrent = $_POST["taketorrent"];
$takeforumpost = $_POST["takeforumpost"];
$takereason = $_POST["reason"];
$takereportofferid = $_POST["takereportofferid"];
$takerequestid = $_POST["takerequestid"];
$takesubtitleid = $_POST["takesubtitleid"];

function takereport($reportid, $type, $reason)
{
	global $CURUSER, $lang_report, $Cache;
	int_check($reportid);
	// Check if takereason is set
	if ($reason == ''){
		stderr($lang_report['std_error'],$lang_report['std_missing_reason']);
		die();
	}
	$res = sql_query("SELECT id FROM reports WHERE addedby = ".sqlesc($CURUSER['id'])." AND reportid= ".sqlesc($reportid)." AND type = ".sqlesc($type)) or sqlerr(__FILE__,__LINE__);
	if (mysql_num_rows($res) == 0)
	{
		$date = sqlesc(date("Y-m-d H:i:s"));

		sql_query("INSERT into reports (addedby,reportid,type,reason,added) VALUES (".sqlesc($CURUSER['id']).",".sqlesc($reportid).",".sqlesc($type).", ".sqlesc(trim($reason)).",".$date.")") or sqlerr(__FILE__,__LINE__);
		$Cache->delete_value('staff_report_count');
		$Cache->delete_value('staff_new_report_count');
		stderr($lang_report['std_message'],$lang_report['std_successfully_reported']);
		die();
	}
	else
	{
		stderr($lang_report['std_error'],$lang_report['std_already_reported_this']);
		die();
	}
}

//////////OFFER #1 START//////////
if (isset($takereportofferid) && isset($takereason))
{
	takereport($takereportofferid, 'offer', $takereason);
}
//////////OFFER #1 END//////////

//////////REQUEST #1 START//////////
elseif ((isset($takerequestid)) && (isset($takereason)))
{
	takereport($takerequestid, 'request', $takereason);
}
//////////REQUEST #1 END//////////

//////////USER #1 START//////////
elseif ((isset($takeuser)) && (isset($takereason)))
{
	takereport($takeuser, 'user', $takereason);
}
//////////USER #1 END//////////

//////////TORRENT #1 START//////////
elseif ((isset($taketorrent)) && (isset($takereason)))
{
	takereport($taketorrent, 'torrent', $takereason);
}
//////////TORRENT #1 END//////////

//////////FORUM POST #1 START//////////
elseif ((isset($takeforumpost)) && (isset($takereason)))
{
	takereport($takeforumpost, 'post', $takereason);
}
//////////FORUM #1 END//////////

//////////COMMENT #1 START//////////
elseif ((isset($takecommentid)) && (isset($takereason)))
{
	takereport($takecommentid, 'comment', $takereason);
}
//////////COMMENT #1 END//////////

//////////SUBTITLE #1 START//////////
elseif ((isset($takesubtitleid)) && (isset($takereason)))
{
	takereport($takesubtitleid, 'subtitle', $takereason);
}
//////////SUBTITLE #1 END//////////

//////////USER #2 START//////////
elseif (isset($user))
{
	int_check($user);
	if ($user == $CURUSER['id']) {
		stderr($lang_report['std_sorry'],$lang_report['std_cannot_report_oneself']);
		die;
	}
	$res = sql_query("SELECT username, class FROM users WHERE id=".sqlesc($user)) or sqlerr(__FILE__,__LINE__);
	if (mysql_num_rows($res) == 0)
	{
		stderr($lang_report['std_error'],$lang_report['std_invalid_user_id']);
		die();
	}

	$arr = mysql_fetch_assoc($res);
	if ($arr["class"] >= $staffmem_class)
	{
		stderr($lang_report['std_sorry'],$lang_report['std_cannot_report'].get_user_class_name($arr["class"],false,true,true), false);
		die();
	}

	else
	{
		stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_user'].get_username(htmlspecialchars($user)).$lang_report['text_to_staff']."<br />".$lang_report['text_not_for_leechers']."<br />".$lang_report['text_reason_note']."<br /><form method=post action=report.php><input type=hidden name=takeuser value=\"".htmlspecialchars($user)."\">".$lang_report['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_report['submit_confirm']."\"></form>", false);
	}
}
//////////USER #2 END//////////

//////////TORRENT #2 START//////////
elseif (isset($torrent))
{
	int_check($torrent);
	$res = sql_query("SELECT name FROM torrents WHERE id=".sqlesc($torrent));

	if (mysql_num_rows($res) == 0)
	{
		stderr($lang_report['std_error'],$lang_report['std_invalid_torrent_id']);
		die();
	}
	$arr = mysql_fetch_array($res);
	stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_torrent']."<a href=details.php?id=".htmlspecialchars($torrent)."><b>".htmlspecialchars($arr['name'])."</b></a>".$lang_report['text_to_staff']."<br />".$lang_report['text_reason_note']."<br /><form method=post action=report.php><input type=hidden name=taketorrent value=\"".htmlspecialchars($torrent)."\">".$lang_report['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_report['submit_confirm']."\"></form>", false);
}
//////////TORRENT #2 END//////////

//////////FORUM POST #2 START//////////
elseif (isset($forumpost))
{
	int_check($forumpost);
	$res = sql_query("SELECT topics.id AS topicid, topics.subject AS subject, posts.userid AS postuserid FROM topics LEFT JOIN posts ON posts.topicid = topics.id WHERE posts.id=".sqlesc($forumpost));

	if (mysql_num_rows($res) == 0)
	{
		stderr($lang_report['std_error'],$lang_report['std_invalid_post_id']);
	}
	$arr = mysql_fetch_array($res);
	stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_post'].$forumpost.$lang_report['text_of_topic']."<a href=\"forums.php?action=viewtopic&topicid=".$arr['topicid']."&page=p".htmlspecialchars($forumpost)."#".htmlspecialchars($forumpost)."\"><b>".htmlspecialchars($arr['subject'])."</b></a>".$lang_report['text_by'].get_username($arr['postuserid']).$lang_report['text_to_staff']."<br />".$lang_report['text_reason_note']."<br /><form method=post action=report.php><input type=hidden name=takeforumpost value=\"".htmlspecialchars($forumpost)."\">".$lang_report['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_report['submit_confirm']."\"></form>", false);
}
//////////FORUM POST #2 END//////////

//////////COMMENT #2 START//////////
elseif (isset($commentid))
{
	int_check($commentid);
	$res = sql_query("SELECT id, user, torrent, request, offer FROM comments WHERE id=".sqlesc($commentid));
	if (mysql_num_rows($res) == 0)
	{
		stderr($lang_report['std_error'],$lang_report['std_invalid_comment_id']);
	}
	$arr = mysql_fetch_array($res);
	if ($arr['torrent']){ //Comment of torrent. BTW, this is shitty code!
		$name = get_single_value("torrents","name","WHERE id=".sqlesc($arr['torrent']));
		$url = "details.php?id=".$arr['torrent']."#".$commentid;
		$of = $lang_report['text_of_torrent'];
	}
	elseif ($arr['offer']){ //Comment of offer
		$name = get_single_value("offers","name","WHERE id=".sqlesc($arr['offer']));
		$url = "offers.php?id=".$arr['offer']."&off_details=1#".$commentid;
		$of = $lang_report['text_of_offer'];
	}
	/*elseif ($arr['request']){ //Comment of request
		$name = get_single_value("requests","request","WHERE id=".sqlesc($arr['request']));
		$url = "viewrequests.php?id=".$arr['request']."&req_details=1#".$commentid;
		$of = $lang_report['text_of_request'];
	}*/
	else //Comment belongs to no one
		stderr($lang_report['std_error'], $lang_report['std_orphaned_comment']);

	stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_comment'].$commentid.$of."<a href=\"".$url."\"><b>".htmlspecialchars($name)."</b></a>".$lang_report['text_by'].get_username($arr['user']).$lang_report['text_to_staff']."<br />".$lang_report['text_reason_note']."<br /><form method=post action=report.php><input type=hidden name=takecommentid value=\"".htmlspecialchars($commentid)."\">".$lang_report['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_report['submit_confirm']."\"></form>", false);
}
//////////COMMENT #2 END//////////

//////////OFFER #2 START//////////
elseif (isset($reportofferid))
{
	int_check($reportofferid);
	$res = sql_query("SELECT id,name FROM offers WHERE id=".sqlesc($reportofferid));
	if (mysql_num_rows($res) == 0)
	{
		stderr($lang_report['std_error'],$lang_report['std_invalid_offer_id']);
	}
	$arr = mysql_fetch_array($res);
	stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_offer']."<a href=\"offers.php?id=".$arr['id']."&off_details=1\"><b>".htmlspecialchars($arr['name'])."</b></a>".$lang_report['text_to_staff']."<br />".$lang_report['text_reason_note']."<br /><form method=post action=report.php><input type=hidden name=takereportofferid value=\"".htmlspecialchars($reportofferid)."\">".$lang_report['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_report['submit_confirm']."\"></form>", false);
}
//////////OFFERT #2 END//////////

//////////REQUEST #2 START//////////
elseif (isset($reportrequestid))
{
	int_check($reportrequestid);
	$res = sql_query("SELECT id,request FROM requests WHERE id=".sqlesc($reportrequestid));
	if (mysql_num_rows($res) == 0)
	{
		stderr($lang_report['std_error'],$lang_report['std_invalid_request_id']);
	}
	$arr = mysql_fetch_array($res);
	stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_request']."<a href=\"viewrequests.php?id=".$arr['id']."&req_details=1\"><b>".htmlspecialchars($arr['request'])."</b></a>".$lang_report['text_to_staff']."<br />".$lang_report['text_reason_note']."<br /><form method=post action=report.php><input type=hidden name=takerequestid value=\"".htmlspecialchars($reportrequestid)."\">".$lang_report['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_report['submit_confirm']."\"></form>", false);
}
//////////REQUEST #2 END//////////

//////////SUBTITLE #2 START//////////
elseif (isset($subtitle))
{
	int_check($subtitle);
	$res = sql_query("SELECT id, torrent_id, title FROM subs WHERE id=".sqlesc($subtitle));
	if (mysql_num_rows($res) == 0)
	{
		stderr($lang_report['std_error'],$lang_report['std_invalid_subtitle_id']);
	}
	$arr = mysql_fetch_array($res);
	stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_subtitle']."<a href=\"downloadsubs.php?torrentid=" . $arr['torrent_id'] ."&subid=" .$arr['id']."\"><b>".htmlspecialchars($arr['title'])."</b></a>".$lang_report['text_to_staff']."<br />".$lang_report['text_reason_note']."<br /><form method=post action=report.php><input type=hidden name=takesubtitleid value=\"".htmlspecialchars($subtitle)."\">".$lang_report['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_report['submit_confirm']."\"></form>", false);
}
//////////SUBTITLE #2 END//////////

else // unknown action
	stderr($lang_report['std_error'],$lang_report['std_invalid_action']);
?>
