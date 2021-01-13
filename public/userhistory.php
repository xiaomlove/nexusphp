<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

parked();
$userid = $_GET["id"];
int_check($userid,true);

if ($CURUSER["id"] != $userid && get_user_class() < $viewhistory_class)
permissiondenied();

$action = htmlspecialchars($_GET["action"]);

//-------- Global variables

$perpage = 15;

//-------- Action: View posts

if ($action == "viewposts")
{
	$select_is = "COUNT(DISTINCT p.id)";

	$from_is = "posts AS p LEFT JOIN topics as t ON p.topicid = t.id LEFT JOIN forums AS f ON t.forumid = f.id";

	$where_is = "p.userid = $userid AND f.minclassread <= " . $CURUSER['class'];

	$order_is = "p.id DESC";

	$query = "SELECT $select_is FROM $from_is WHERE $where_is";

	$res = sql_query($query) or sqlerr(__FILE__, __LINE__);

	$arr = mysql_fetch_row($res) or stderr($lang_userhistory['std_error'], $lang_userhistory['std_no_posts_found']);

	$postcount = $arr[0];

	//------ Make page menu

	list($pagertop, $pagerbottom, $limit) = pager($perpage, $postcount, $_SERVER["PHP_SELF"] . "?action=viewposts&id=$userid&");

	//------ Get user data

	$res = sql_query("SELECT username, donor, warned, enabled FROM users WHERE id=$userid") or sqlerr(__FILE__, __LINE__);

	if (mysql_num_rows($res) == 1)
	{
		$arr = mysql_fetch_assoc($res);

		$subject = get_username($userid);
	}
	else
	$subject = "unknown[$userid]";

	//------ Get posts

	$from_is = "posts AS p LEFT JOIN topics as t ON p.topicid = t.id LEFT JOIN forums AS f ON t.forumid = f.id LEFT JOIN readposts as r ON p.topicid = r.topicid AND p.userid = r.userid";

	$select_is = "f.id AS f_id, f.name, t.id AS t_id, t.subject, t.lastpost, r.lastpostread, p.*";

	$query = "SELECT $select_is FROM $from_is WHERE $where_is ORDER BY $order_is $limit";

	$res = sql_query($query) or sqlerr(__FILE__, __LINE__);

	if (mysql_num_rows($res) == 0) stderr($lang_userhistory['std_error'], $lang_userhistory['std_no_posts_found']);

	stdhead($lang_userhistory['head_posts_history']);

	print("<h1>".$lang_userhistory['text_posts_history_for'].$subject."</h1>\n");

	if ($postcount > $perpage) echo $pagertop;

	//------ Print table

	begin_main_frame();

	begin_frame();

	while ($arr = mysql_fetch_assoc($res))
	{
		$postid = $arr["id"];

		$posterid = $arr["userid"];

		$topicid = $arr["t_id"];

		$topicname = $arr["subject"];

		$forumid = $arr["f_id"];

		$forumname = $arr["name"];

		$newposts = ($arr["lastpostread"] < $arr["lastpost"]) && $CURUSER["id"] == $userid;

		$added = gettime($arr["added"], true, false, false);

		print("<p class=sub><table border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
	    $added&nbsp;--&nbsp;".$lang_userhistory['text_forum'].
	    "<a href=forums.php?action=viewforum&forumid=$forumid>$forumname</a>
	    &nbsp;--&nbsp;".$lang_userhistory['text_topic'].
	    "<a href=forums.php?action=viewtopic&topicid=$topicid>$topicname</a>
      &nbsp;--&nbsp;".$lang_userhistory['text_post'].
      "<a href=forums.php?action=viewtopic&topicid=$topicid&page=p$postid#pid$postid>#$postid</a>" .
      ($newposts ? " &nbsp;<b>(<font class=new>".$lang_userhistory['text_new']."</font>)</b>" : "") .
      "</td></tr></table></p>\n");

      print("<br />");
      
      print("<table class=main width=100% border=1 cellspacing=0 cellpadding=5>\n");

      $body = format_comment($arr["body"]);

      if (is_valid_id($arr['editedby']))
      {
      	$subres = sql_query("SELECT username FROM users WHERE id=$arr[editedby]");
      	if (mysql_num_rows($subres) == 1)
      	{
      		$subrow = mysql_fetch_assoc($subres);
      		$body .= "<p><font size=1 class=small>".$lang_userhistory['text_last_edited'].get_username($arr['editedby']).$lang_userhistory['text_at']."$arr[editdate]</font></p>\n";
      	}
      }

      print("<tr valign=top><td class=comment>$body</td></tr>\n");

      print("</td></tr></table>\n");
      print("<br />");
	}

	end_frame();

	end_main_frame();

	if ($postcount > $perpage) echo $pagerbottom;

	stdfoot();

	die;
}

//-------- Action: View comments

if ($action == "viewcomments")
{
	$select_is = "COUNT(*)";

	// LEFT due to orphan comments
	$from_is = "comments AS c LEFT JOIN torrents as t
	            ON c.torrent = t.id";

	$where_is = "c.user = $userid";
	$order_is = "c.id DESC";

	$query = "SELECT $select_is FROM $from_is WHERE $where_is ORDER BY $order_is";

	$res = sql_query($query) or sqlerr(__FILE__, __LINE__);

	$arr = mysql_fetch_row($res) or stderr($lang_userhistory['std_error'], $lang_userhistory['std_no_comments_found']);

	$commentcount = $arr[0];

	//------ Make page menu

	list($pagertop, $pagerbottom, $limit) = pager($perpage, $commentcount, $_SERVER["PHP_SELF"] . "?action=viewcomments&id=$userid&");

	//------ Get user data

	$res = sql_query("SELECT username, donor, warned, enabled FROM users WHERE id=$userid") or sqlerr(__FILE__, __LINE__);

	if (mysql_num_rows($res) == 1)
	{
		$arr = mysql_fetch_assoc($res);

		$subject = get_username($userid);
	}
	else
	$subject = "unknown[$userid]";

	//------ Get comments

	$select_is = "t.name, c.torrent AS t_id, c.id, c.added, c.text";

	$query = "SELECT $select_is FROM $from_is WHERE $where_is ORDER BY $order_is $limit";

	$res = sql_query($query) or sqlerr(__FILE__, __LINE__);

	if (mysql_num_rows($res) == 0) stderr($lang_userhistory['std_error'], $lang_userhistory['std_no_comments_found']);

	stdhead($lang_userhistory['head_comments_history']);

	print("<h1>".$lang_userhistory['text_comments_history_for']."$subject</h1>\n");

	if ($commentcount > $perpage) echo $pagertop;

	//------ Print table

	begin_main_frame();

	begin_frame();

	while ($arr = mysql_fetch_assoc($res))
	{

		$commentid = $arr["id"];

		$torrent = $arr["name"];

		// make sure the line doesn't wrap
		if (strlen($torrent) > 55) $torrent = substr($torrent,0,52) . "...";

		$torrentid = $arr["t_id"];

		//find the page; this code should probably be in details.php instead

		$subres = sql_query("SELECT COUNT(*) FROM comments WHERE torrent = $torrentid AND id < $commentid")
		or sqlerr(__FILE__, __LINE__);
		$subrow = mysql_fetch_row($subres);
		$count = $subrow[0];
		$comm_page = floor($count/20);
		$page_url = $comm_page?"&page=$comm_page":"";

		$added = gettime($arr["added"], true, false, false);

		print("<p class=sub><table border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>".
		"$added&nbsp;---&nbsp;".$lang_userhistory['text_torrent'].
		($torrent?("<a href=details.php?id=$torrentid&tocomm=1&hit=1>$torrent</a>"):" [Deleted] ").
		"&nbsp;---&nbsp;".$lang_userhistory['text_comment']."</b>#<a href=details.php?id=$torrentid&tocomm=1&hit=1$page_url>$commentid</a>
	  </td></tr></table></p>\n");
		print("<br />");
		
		print("<table class=main width=100% border=1 cellspacing=0 cellpadding=5>\n");

		$body = format_comment($arr["text"]);

		print("<tr valign=top><td class=comment>$body</td></tr>\n");

		print("</td></tr></table>\n");
		
		print("<br />");
	}

	end_frame();

	end_main_frame();

	if ($commentcount > $perpage) echo $pagerbottom;

	stdfoot();

	die;
}

//-------- Handle unknown action

if ($action != "")
stderr($lang_userhistory['std_history_error'], $lang_userhistory['std_unkown_action']);

//-------- Any other case

stderr($lang_userhistory['std_history_error'], $lang_userhistory['std_invalid_or_no_query']);

?>
