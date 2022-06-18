<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();
if ($enableextforum == 'yes') //check whether internal forum is disabled
	permissiondenied();

// ------------- start: functions ------------------//
//print forum stats
function forum_stats ()
{
	global $lang_forums, $Cache, $today_date;

	if (!$activeforumuser_num = $Cache->get_value('active_forum_user_count')){
		$secs = 900;
		$dt = date("Y-m-d H:i:s",(TIMENOW - $secs));
		$activeforumuser_num = get_row_count("users","WHERE forum_access >= ".sqlesc($dt));
		$Cache->cache_value('active_forum_user_count', $activeforumuser_num, 300);
	}
	if ($activeforumuser_num){
		$forumusers = $lang_forums['text_there'].is_or_are($activeforumuser_num)."<b>".$activeforumuser_num."</b>".$lang_forums['text_online_user'].add_s($activeforumuser_num).$lang_forums['text_in_forum_now'];
	}
	else
		$forumusers = $lang_forums['text_no_active_users'];
?>
<h2 align="left"><?php echo $lang_forums['text_stats'] ?></h2>
<table width="100%"><tr><td class="text">
<?php
	if (!$postcount = $Cache->get_value('total_posts_count')){
		$postcount = get_row_count("posts");
		$Cache->cache_value('total_posts_count', $postcount, 96400);
	}
	if (!$topiccount = $Cache->get_value('total_topics_count')){
		$topiccount = get_row_count("topics");
		$Cache->cache_value('total_topics_count', $topiccount, 96500);
	}
	if (!$todaypostcount = $Cache->get_value('today_'.$today_date.'_posts_count')) {
		$todaypostcount = get_row_count("posts", "WHERE added > ".sqlesc(date("Y-m-d")));
		$Cache->cache_value('today_'.$today_date.'_posts_count', $todaypostcount, 700);
	}
	print($lang_forums['text_our_members_have'] ."<b>".$postcount."</b>". $lang_forums['text_posts_in_topics']."<b>".$topiccount."</b>".$lang_forums['text_in_topics']."<b><font class=\"new\">".$todaypostcount."</font></b>".$lang_forums['text_new_post'].add_s($todaypostcount).$lang_forums['text_posts_today']."<br /><br />");
	print($forumusers);
?>
</td></tr></table>
<?php
}

//set all topics as read
function catch_up()
{
	global $CURUSER, $Cache;

	if (!$CURUSER)
		die;
	sql_query("DELETE FROM readposts WHERE userid=".sqlesc($CURUSER['id']));
	$Cache->delete_value('user_'.$CURUSER['id'].'_last_read_post_list');
	$lastpostid=get_single_value("posts","id","ORDER BY id DESC");
	if ($lastpostid){
		$CURUSER['last_catchup'] = $lastpostid;
		sql_query("UPDATE users SET last_catchup = ".sqlesc($lastpostid)." WHERE id=".sqlesc($CURUSER['id']));
	}
}

//return image
function get_topic_image($status= "read"){
	global $lang_forums;
	switch($status){
		case "read": {
			return "<img class=\"unlocked\" src=\"pic/trans.gif\" alt=\"read\" title=\"".$lang_forums['title_read']."\" />";
			break;
			}
		case "unread": {
			return "<img class=\"unlockednew\" src=\"pic/trans.gif\" alt=\"unread\" title=\"".$lang_forums['title_unread']."\" />";
			break;
		}
		case "locked": {
			return "<img class=\"locked\" src=\"pic/trans.gif\" alt=\"locked\" title=\"".$lang_forums['title_locked']."\" />";
			break;
		}
		case "lockednew": {
			return "<img class=\"lockednew\" src=\"pic/trans.gif\" alt=\"lockednew\" title=\"".$lang_forums['title_locked_new']."\" />";
			break;
		}
	}
}

function highlight_topic($subject, $hlcolor=0)
{
	$colorname=get_hl_color($hlcolor);
	if ($colorname)
		$subject = "<b><font color=\"".$colorname."\">".$subject."</font></b>";
	return $subject;
}

function check_whether_exist($id, $place='forum'){
	global $lang_forums;
	int_check($id,true);
	switch ($place){
		case 'forum':
		{
			$count = get_row_count("forums","WHERE id=".sqlesc($id));
			if (!$count)
				stderr($lang_forums['std_error'],$lang_forums['std_no_forum_id']);
			break;
		}
		case 'topic':
		{
			$count = get_row_count("topics","WHERE id=".sqlesc($id));
			if (!$count)
				stderr($lang_forums['std_error'],$lang_forums['std_bad_topic_id']);
			$forumid = get_single_value("topics","forumid","WHERE id=".sqlesc($id));
			check_whether_exist($forumid, 'forum');
			break;
		}
		case 'post':
		{
			$count = get_row_count("posts","WHERE id=".sqlesc($id));
			if (!$count)
				stderr($lang_forums['std_error'],$lang_forums['std_no_post_id']);
			$topicid = get_single_value("posts","topicid","WHERE id=".sqlesc($id));
			check_whether_exist($topicid, 'topic');
			break;
		}
	}
}

//update the last post of a topic
function update_topic_last_post($topicid)
{
	global $lang_forums;
	$res = sql_query("SELECT id FROM posts WHERE topicid=".sqlesc($topicid)." ORDER BY id DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
	$arr = mysql_fetch_row($res) or die($lang_forums['std_no_post_found']);
	$postid = $arr[0];
	sql_query("UPDATE topics SET lastpost=".sqlesc($postid)." WHERE id=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
}

function get_forum_row($forumid = 0)
{
	global $Cache;
	if (!$forums = $Cache->get_value('forums_list')){
		$forums = array();
		$res2 = sql_query("SELECT * FROM forums ORDER BY forid ASC, sort ASC") or sqlerr(__FILE__, __LINE__);
		while ($row2 = mysql_fetch_array($res2))
			$forums[$row2['id']] = $row2;
		$Cache->cache_value('forums_list', $forums, 86400);
	}
	if (!$forumid)
		return $forums;
	else return $forums[$forumid];
}
function get_last_read_post_id($topicid) {
	global $CURUSER, $Cache;
	static $ret;
	if (!$ret && !$ret = $Cache->get_value('user_'.$CURUSER['id'].'_last_read_post_list')){
		$ret = array();
		$res = sql_query("SELECT * FROM readposts WHERE userid=" . sqlesc($CURUSER['id']));
		if (mysql_num_rows($res) != 0){
			while ($row = mysql_fetch_array($res))
			$ret[$row['topicid']] = $row['lastpostread'];
			$Cache->cache_value('user_'.$CURUSER['id'].'_last_read_post_list', $ret, 900);
		}
		else $Cache->cache_value('user_'.$CURUSER['id'].'_last_read_post_list', 'no record', 900);
	}
	if ($ret != "no record" && isset($ret[$topicid]) && $CURUSER['last_catchup'] < $ret[$topicid]){
		return $ret[$topicid];
	}
	elseif ($CURUSER['last_catchup'])
		return $CURUSER['last_catchup'];
	else return 0;
}

//-------- Inserts a compose frame
function insert_compose_frame($id, $type = 'new')
{
	global $maxsubjectlength, $CURUSER;
	global $lang_forums;
	$hassubject = false;
	$subject = "";
	$body = "";
	print("<form id=\"compose\" method=\"post\" name=\"compose\" action=\"?action=post\">\n");
	switch ($type){
		case 'new':
		{
			$forumname = get_single_value("forums","name","WHERE id=".sqlesc($id));
			$title = $lang_forums['text_new_topic_in']." <a href=\"".htmlspecialchars("?action=viewforum&forumid=".$id)."\">".htmlspecialchars($forumname)."</a> ".$lang_forums['text_forum'];
			$hassubject = true;
			break;
		}
		case 'reply':
		{
			$topicname = get_single_value("topics","subject","WHERE id=".sqlesc($id));
			$title = $lang_forums['text_reply_to_topic']." <a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$id)."\">".htmlspecialchars($topicname)."</a> ";
			break;
		}
		case 'quote':
		{
			$topicid=get_single_value("posts","topicid","WHERE id=".sqlesc($id));
			$topicname = get_single_value("topics","subject","WHERE id=".sqlesc($topicid));
			$title = $lang_forums['text_reply_to_topic']." <a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$topicid)."\">".htmlspecialchars($topicname)."</a> ";
			$res = sql_query("SELECT posts.body, users.username FROM posts LEFT JOIN users ON posts.userid = users.id WHERE posts.id=$id") or sqlerr(__FILE__, __LINE__);
			if (mysql_num_rows($res) != 1)
				stderr($lang_forums['std_error'], $lang_forums['std_no_post_id']);
			$arr = mysql_fetch_assoc($res);
			$body = "[quote=".htmlspecialchars($arr["username"])."]".htmlspecialchars(unesc($arr["body"]))."[/quote]";
			$id = $topicid;
			$type = 'reply';
			break;
		}
		case 'edit':
		{
			$res = sql_query("SELECT topicid, body FROM posts WHERE id=".sqlesc($id)." LIMIT 1") or sqlerr(__FILE__, __LINE__);
			$row = mysql_fetch_array($res);
			$topicid=$row['topicid'];
			$firstpost = get_single_value("posts","MIN(id)", "WHERE topicid=".sqlesc($topicid));
			if ($firstpost == $id){
				$subject = get_single_value("topics","subject","WHERE id=".sqlesc($topicid));
				$hassubject = true;
			}
			$body = htmlspecialchars(unesc($row["body"]));
			$title = $lang_forums['text_edit_post'];
			break;
		}
		default:
		{
			die;
		}
	}
	print("<input type=\"hidden\" name=\"id\" value=\"".$id."\" />");
	print("<input type=\"hidden\" name=\"type\" value=\"".$type."\" />");
	begin_compose($title, $type, $body, $hassubject, $subject);
	end_compose();
	print("</form>");
}
// ------------- end: functions ------------------//
// ------------- start: Global variables ------------------//
$maxsubjectlength = 100;
$postsperpage = $CURUSER["postsperpage"];
if (!$postsperpage){
	if (is_numeric($forumpostsperpage))
		$postsperpage = $forumpostsperpage;//system-wide setting
	else $postsperpage = 10;
}
//get topics per page
$topicsperpage = $CURUSER["topicsperpage"];
if (!$topicsperpage){
	if (is_numeric($forumtopicsperpage_main))
		$topicsperpage = $forumtopicsperpage_main;//system-wide setting
	else $topicsperpage = 20;
}
$today_date = date("Y-m-d",TIMENOW);
// ------------- end: Global variables ------------------//

$action = htmlspecialchars(trim($_GET["action"] ?? ''));

//-------- Action: New topic
if ($action == "newtopic")
{
	$forumid = intval($_GET["forumid"] ?? 0);
	check_whether_exist($forumid, 'forum');
	stdhead($lang_forums['head_new_topic']);
	begin_main_frame();
	insert_compose_frame($forumid,'new');
	end_main_frame();
	stdfoot();
	die;
}
if ($action == "quotepost")
{
	$postid = intval($_GET["postid"] ?? 0);
	check_whether_exist($postid, 'post');
	stdhead($lang_forums['head_post_reply']);
	begin_main_frame();
	insert_compose_frame($postid, 'quote');
	end_main_frame();
	stdfoot();
	die;
}

//-------- Action: Reply

if ($action == "reply")
{
	$topicid = intval($_GET["topicid"] ?? 0);
	check_whether_exist($topicid, 'topic');
	stdhead($lang_forums['head_post_reply']);
	begin_main_frame();
	insert_compose_frame($topicid, 'reply');
	end_main_frame();
	stdfoot();
	die;
}

//-------- Action: Edit post

if ($action == "editpost")
{
	$postid = intval($_GET["postid"] ?? 0);
	check_whether_exist($postid, 'post');

	$res = sql_query("SELECT userid, topicid FROM posts WHERE id=".sqlesc($postid)) or sqlerr(__FILE__, __LINE__);
	$arr = mysql_fetch_assoc($res);

	$res2 = sql_query("SELECT locked FROM topics WHERE id = " . $arr["topicid"]) or sqlerr(__FILE__, __LINE__);
	$arr2 = mysql_fetch_assoc($res2);
	$locked = ($arr2["locked"] == 'yes');

	$ismod = is_forum_moderator($postid, 'post');
	if (($CURUSER["id"] != $arr["userid"] || $locked) && get_user_class() < $postmanage_class && !$ismod)
		permissiondenied();

	stdhead($lang_forums['text_edit_post']);
	begin_main_frame();
	insert_compose_frame($postid, 'edit');
	end_main_frame();
	stdfoot();
	die;
}

//-------- Action: Post
if ($action == "post")
{
	if ($CURUSER["forumpost"] == 'no')
	{
		stderr($lang_forums['std_sorry'], $lang_forums['std_unauthorized_to_post'],false);
		die;
	}
	$id = $_POST["id"];
	$type = $_POST["type"];
	$subject = $_POST["subject"] ?? '';
	$body = trim($_POST["body"]);
	$hassubject = false;
	switch ($type){
		case 'new':
		{
			check_whether_exist($id, 'forum');
			$forumid = $id;
			$hassubject = true;
			break;
		}
		case 'reply':
		{
			check_whether_exist($id, 'topic');
			$topicid = $id;
			$forumid = get_single_value("topics", "forumid", "WHERE id=".sqlesc($topicid));
			break;
		}
		case 'edit':
		{
			check_whether_exist($id, 'post');
			$res = sql_query("SELECT topicid FROM posts WHERE id=".sqlesc($id)." LIMIT 1") or sqlerr(__FILE__, __LINE__);
			$row = mysql_fetch_array($res);
			$topicid=$row['topicid'];
			$forumid = get_single_value("topics", "forumid", "WHERE id=".sqlesc($topicid));
			$firstpost = get_single_value("posts","MIN(id)", "WHERE topicid=".sqlesc($topicid));
			if ($firstpost == $id){
				$hassubject = true;
			}
			break;
		}
		default:
		{
			die;
		}
	}

	if ($hassubject){
		$subject = trim($subject);
		if (!$subject)
			stderr($lang_forums['std_error'], $lang_forums['std_must_enter_subject']);
		if (strlen($subject) > $maxsubjectlength)
			stderr($lang_forums['std_error'], $lang_forums['std_subject_limited']);
	}

	//------ Make sure sure user has write access in forum
	$arr = get_forum_row($forumid) or die($lang_forums['std_bad_forum_id']);

	if (get_user_class() < $arr["minclasswrite"] || ($type =='new' && get_user_class() < $arr["minclasscreate"]))
		permissiondenied();

	if ($body == "")
		stderr($lang_forums['std_error'], $lang_forums['std_no_body_text']);

	$userid = intval($CURUSER["id"] ?? 0);
	$date = date("Y-m-d H:i:s");

	if ($type != 'new'){
		//---- Make sure topic is unlocked

		$res = sql_query("SELECT locked FROM topics WHERE id=$topicid") or sqlerr(__FILE__, __LINE__);
		$arr = mysql_fetch_assoc($res) or die("Topic id n/a");
		if ($arr["locked"] == 'yes' && get_user_class() < $postmanage_class && !is_forum_moderator($topicid, 'topic'))
			stderr($lang_forums['std_error'], $lang_forums['std_topic_locked']);
	}

	if ($type == 'edit')
	{
		if ($hassubject){
			sql_query("UPDATE topics SET subject=".sqlesc($subject)." WHERE id=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
			$forum_last_replied_topic_row = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content');
			if ($forum_last_replied_topic_row && $forum_last_replied_topic_row['id'] == $topicid)
				$Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
		}
		sql_query("UPDATE posts SET body=".sqlesc($body).", editdate=".sqlesc($date).", editedby=".sqlesc($CURUSER['id'])." WHERE id=".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
		$postid = $id;
		$Cache->delete_value('post_'.$postid.'_content');
        //send pm
        $topicInfo = \App\Models\Topic::query()->findOrFail($topicid);
        $postInfo = \App\Models\Post::query()->findOrFail($id);
        $postUrl = sprintf('[url=forums.php?action=viewtopic&topicid=%s&page=p%s#pid%s]%s[/url]', $topicid, $id, $id, $topicInfo->subject);
        if ($postInfo->userid != $CURUSER['id']) {
            $receiver = $postInfo->user;
            $locale = $receiver->locale;
            $notify = [
                'sender' => 0,
                'receiver' => $receiver->id,
                'subject' => nexus_trans('forum.post.edited_notify_subject', [], $locale),
                'msg' => nexus_trans('forum.post.edited_notify_body', ['topic_subject' => $postUrl, 'editor' => $CURUSER['username']], $locale),
                'added' => now(),
            ];
            \App\Models\Message::query()->insert($notify);
            \Nexus\Database\NexusDB::cache_del("user_{$postInfo->userid}_unread_message_count");
            \Nexus\Database\NexusDB::cache_del("user_{$postInfo->userid}_inbox_count");
        }
	}
	else
	{
		// Anti Flood Code
		// To ensure that posts are not entered within 10 seconds limiting posts
		// to a maximum of 360*6 per hour.
		if (get_user_class() < $postmanage_class) {
			if (strtotime($CURUSER['last_post']) > (TIMENOW - 10))
			{
				$secs = 10 - (TIMENOW - strtotime($CURUSER['last_post']));
				stderr($lang_forums['std_error'],$lang_forums['std_post_flooding'].$secs.$lang_forums['std_seconds_before_making'],false);
			}
		}
		if ($type == 'new'){ //new topic
			//add bonus
			KPS("+",$starttopic_bonus,$userid);

			//---- Create topic
			sql_query("INSERT INTO topics (userid, forumid, subject) VALUES($userid, $forumid, ".sqlesc($subject).")") or sqlerr(__FILE__, __LINE__);
			$topicid = mysql_insert_id() or stderr($lang_forums['std_error'],$lang_forums['std_no_topic_id_returned']);
			sql_query("UPDATE forums SET topiccount=topiccount+1, postcount=postcount+1 WHERE id=".sqlesc($forumid));
		}
		else // new post
		{
			//add bonus
			KPS("+",$makepost_bonus,$userid);
			sql_query("UPDATE forums SET postcount=postcount+1 WHERE id=".sqlesc($forumid));
		}

		sql_query("INSERT INTO posts (topicid, userid, added, body, ori_body) VALUES ($topicid, $userid, ".sqlesc($date).", ".sqlesc($body).", ".sqlesc($body).")") or sqlerr(__FILE__, __LINE__);
		$postid = mysql_insert_id() or die($lang_forums['std_post_id_not_available']);
		//send pm
        $topicInfo = \App\Models\Topic::query()->findOrFail($topicid);
        $postUrl = sprintf('[url=forums.php?action=viewtopic&topicid=%s&page=p%s#pid%s]%s[/url]', $topicid, $postid, $postid, $topicInfo->subject);
        if ($type == 'reply' && $topicInfo->userid != $CURUSER['id']) {
            $receiver = $topicInfo->user;
            $locale = $receiver->locale;
            $notify = [
                'sender' => 0,
                'receiver' => $receiver->id,
                'subject' => nexus_trans('forum.topic.replied_notify_subject', [], $locale),
                'msg' => nexus_trans('forum.topic.replied_notify_body', ['topic_subject' => $postUrl], $locale),
                'added' => now(),
            ];
            \App\Models\Message::query()->insert($notify);
            \Nexus\Database\NexusDB::cache_del("user_{$topicInfo->userid}_unread_message_count");
            \Nexus\Database\NexusDB::cache_del("user_{$topicInfo->userid}_inbox_count");
        }

		$Cache->delete_value('forum_'.$forumid.'_post_'.$today_date.'_count');
		$Cache->delete_value('today_'.$today_date.'_posts_count');
		$Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
		$Cache->delete_value('topic_'.$topicid.'_post_count');
		$Cache->delete_value('user_'.$userid.'_post_count');

		if ($type == 'new')
		{
			// update the first post of topic
			sql_query("UPDATE topics SET firstpost=$postid, lastpost=$postid WHERE id=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
		}
		else
		{
			sql_query("UPDATE topics SET lastpost=$postid WHERE id=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);
		}
		sql_query("UPDATE users SET last_post=".sqlesc($date)." WHERE id=".sqlesc($CURUSER['id'])) or sqlerr(__FILE__, __LINE__);
	}

	//------ All done, redirect user to the post

	$headerstr = "Location: " . get_protocol_prefix() . "$BASEURL/forums.php?action=viewtopic&topicid=$topicid";

	if ($type == 'edit')
		header($headerstr."&page=p".$postid."#pid".$postid);
	else
		header($headerstr."&page=last#pid$postid");
	die;
}

//-------- Action: View topic

if ($action == "viewtopic")
{
	$highlight = htmlspecialchars(trim($_GET["highlight"] ?? ''));

	$topicid = intval($_GET["topicid"] ?? 0);
	int_check($topicid,true);
	$page = $_GET["page"] ?? 0;
	$authorid = intval($_GET["authorid"] ?? 0);
	if ($authorid)
	{
		$where = "WHERE topicid=".sqlesc($topicid)." AND userid=".sqlesc($authorid);
		$addparam = "action=viewtopic&topicid=".$topicid."&authorid=".$authorid;
	}
	else
	{
		$where = "WHERE topicid=".sqlesc($topicid);
		$addparam = "action=viewtopic&topicid=".$topicid;
	}
	$userid = $CURUSER["id"];

	//------ Get topic info

	$res = sql_query("SELECT * FROM topics WHERE id=".sqlesc($topicid)." LIMIT 1") or sqlerr(__FILE__, __LINE__);
	$arr = mysql_fetch_assoc($res) or stderr($lang_forums['std_forum_error'], $lang_forums['std_topic_not_found']);

	$forumid = $arr['forumid'];
	$locked = $arr['locked'] == "yes";
	$orgsubject = $arr['subject'];
	$subject = htmlspecialchars($arr['subject']);
	if ($highlight){
		$subject = highlight($highlight,$orgsubject);
	}
	$sticky = $arr['sticky'] == "yes";
	$hlcolor = $arr['hlcolor'];
	$views = $arr['views'];
	$forumid = $arr["forumid"];

	$row = get_forum_row($forumid);
	//------ Get forum name, moderators
	$forumname = $row['name'];
	$is_forummod = is_forum_moderator($forumid,'forum');

	if (get_user_class() < $row["minclassread"])
		stderr($lang_forums['std_error'], $lang_forums['std_unpermitted_viewing_topic']);
	if (((get_user_class() >= $row["minclasswrite"] && !$locked) || get_user_class() >= $postmanage_class || $is_forummod) && $CURUSER["forumpost"] == 'yes')
		$maypost = true;
	else $maypost = false;

	//------ Update hits column
	sql_query("UPDATE topics SET views = views + 1 WHERE id=$topicid") or sqlerr(__FILE__, __LINE__);

	//------ Get post count
	$postcount = get_row_count("posts",$where);
	if (!$authorid)
		$Cache->cache_value('topic_'.$topicid.'_post_count', $postcount, 3600);

	//------ Make page menu

	$pagerarr = array();

	$perpage = $postsperpage;

	$pages = ceil($postcount / $perpage);

	if (isset($page[0]) && $page[0] == "p")
	{
		$findpost = substr($page, 1);
		$res = sql_query("SELECT id FROM posts $where ORDER BY added") or sqlerr(__FILE__, __LINE__);
		$i = 0;
		while ($arr = mysql_fetch_row($res))
		{
			if ($arr[0] == $findpost)
			break;
			++$i;
		}
		$page = floor($i / $perpage);
	}
	if ($page === "last"){
	$page = $pages-1;
	}
	elseif(isset($page))
	{
		if($page < 0){
		$page = 0;
		}
		elseif ($page > $pages - 1){
		$page = $pages - 1;
		}
	}
	else {if ($CURUSER["clicktopic"] == "firstpage")
		$page = 0;
		else $page = $pages-1;
	}

	$offset = $page * $perpage;
	$dotted = 0;
	$dotspace = 3;
	$dotend = $pages - $dotspace;
	$curdotend = $page - $dotspace;
	$curdotstart = $page + $dotspace;
	for ($i = 0; $i < $pages; ++$i)
	{
		if (($i >= $dotspace && $i <= $curdotend) || ($i >= $curdotstart && $i < $dotend)) {
				if (!$dotted)
				$pagerarr[] = "...";
				$dotted = 1;
				continue;
		}
		$dotted = 0;
		if ($i != $page)
		$pagerarr[] .= "<a href=\"".htmlspecialchars("?".$addparam."&page=".$i)."\"><b>".($i+1)."</b></a>\n";
		else
		$pagerarr[] .= "<font class=\"gray\"><b>".($i+1)."</b></font>\n";
	}
	if ($page == 0)
	$pager = "<font class=\"gray\"><b>&lt;&lt;".$lang_forums['text_prev']."</b></font>";
	else
	$pager = "<a href=\"".htmlspecialchars("?".$addparam."&page=" . ($page - 1)) .
	"\"><b>&lt;&lt;".$lang_forums['text_prev']."</b></a>";
	$pager .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	if ($page == $pages-1)
	$pager .= "<font class=\"gray\"><b>".$lang_forums['text_next']." &gt;&gt;</b></font>\n";
	else
	$pager .= "<a href=\"".htmlspecialchars("?".$addparam."&page=" . ($page + 1)) .
	"\"><b>".$lang_forums['text_next']." &gt;&gt;</b></a>\n";

	$pagerstr = join(" | ", $pagerarr);
	$pagertop = "<p align=\"center\">".$pager."<br />".$pagerstr."</p>\n";
	$pagerbottom = "<p align=\"center\">".$pagerstr."<br />".$pager."</p>\n";
	//------ Get posts

	$res = sql_query("SELECT * FROM posts $where ORDER BY id LIMIT $offset,$perpage") or sqlerr(__FILE__, __LINE__);

	stdhead($lang_forums['head_view_topic']." \"".$orgsubject."\"");
	begin_main_frame("",true);

	print("<h1 align=\"center\"><a class=\"faqlink\" href=\"forums.php\">".$SITENAME."&nbsp;".$lang_forums['text_forums']."</a>--><a class=\"faqlink\" href=\"".htmlspecialchars("?action=viewforum&forumid=".$forumid)."\">".$forumname."</a><b>--></b><span id=\"top\">".$subject.($locked ? "&nbsp;&nbsp;<b>[<font class=\"striking\">".$lang_forums['text_locked']."</font>]</b>" : "")."</span></h1>\n");
	end_main_frame();
	print($pagertop);

	//------ Print table

	begin_main_frame();
	print("<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\"><tr>\n");
	print("<td class=\"embedded\" width=\"99%\">&nbsp;&nbsp;".$lang_forums['there_is']."<b>".$views."</b>".$lang_forums['hits_on_this_topic']);
	print("</td>\n");
	print("<td class=\"embedded nowrap\" width=\"1%\" align=\"right\">");
	if ($maypost)
	{
		print("<a href=\"".htmlspecialchars("?action=reply&topicid=".$topicid)."\"><img class=\"f_reply\" src=\"pic/trans.gif\" alt=\"Add Reply\" title=\"".$lang_forums['title_reply_directly']."\" /></a>&nbsp;&nbsp;");
	}
	print("</td>");
	print("</tr></table>\n");
	begin_frame();

	$pc = mysql_num_rows($res);
	$allPosts = $uidArr = [];
    while ($arr = mysql_fetch_assoc($res)) {
        $allPosts[] = $arr;
        $uidArr[$arr['userid']] = 1;
    }
    $uidArr = array_keys($uidArr);
    unset($arr);
    $neededColumns = array('id', 'noad', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor', 'leechwarn', 'warned', 'title');
    $userInfoArr = \App\Models\User::query()->with(['wearing_medals'])->find($uidArr, $neededColumns)->keyBy('id');
	$pn = 0;
	$lpr = get_last_read_post_id($topicid);
	if ($Advertisement->enable_ad())
		$forumpostad=$Advertisement->get_ad('forumpost');
	foreach ($allPosts as $arr)
	{
		if ($pn>=1)
		{
			if ($Advertisement->enable_ad()){
				if (!empty($forumpostad[$pn-1]))
				echo "<div align=\"center\" style=\"margin-top: 10px\" id=\"\">".$forumpostad[$pn-1]."</div>";
			}
		}
		++$pn;

		$postid = $arr["id"];
		$posterid = $arr["userid"];

		$added = gettime($arr["added"],true,false);

		//---- Get poster details

//		$arr2 = get_user_row($posterid);
		$userInfo = $userInfoArr->get($posterid) ?: \App\Models\User::defaultUser();

		$arr2 = $userInfo->toArray();

		$uploaded = mksize($arr2["uploaded"]);
		$downloaded = mksize($arr2["downloaded"]);
		$ratio = get_ratio($arr2['id']);

		if (!$forumposts = $Cache->get_value('user_'.$posterid.'_post_count')){
			$forumposts = get_row_count("posts","WHERE userid=".$posterid);
			$Cache->cache_value('user_'.$posterid.'_post_count', $forumposts, 3600);
		}

		$signature = ($CURUSER["signatures"] == "yes" ? $arr2["signature"] : "");
		$avatar = ($CURUSER["avatars"] == "yes" ? htmlspecialchars($arr2["avatar"]) : "");

		$uclass = get_user_class_image($arr2["class"]);
		$by = build_medal_image($userInfo->wearing_medals, 20) . get_username($posterid,false,true,true,false,false,true);

		if (!$avatar)
			$avatar = "pic/default_avatar.png";

		if ($pn == $pc)
		{
			print("<span id=\"last\"></span>\n");
			if ($postid > $lpr){
				if ($lpr == $CURUSER['last_catchup']) // There is no record of this topic
					sql_query("INSERT INTO readposts(userid, topicid, lastpostread) VALUES (".$userid.", ".$topicid.", ".$postid.")") or sqlerr(__FILE__, __LINE__);
				elseif ($lpr > $CURUSER['last_catchup']) //There is record of this topic
					sql_query("UPDATE readposts SET lastpostread=$postid WHERE userid=$userid AND topicid=$topicid") or sqlerr(__FILE__, __LINE__);
				$Cache->delete_value('user_'.$CURUSER['id'].'_last_read_post_list');
			}
		}

		print("<div style=\"margin-top: 8pt; margin-bottom: 8pt;\"><table id=\"pid".$postid."\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\"><tr><td class=\"embedded\" width=\"99%\"><a href=\"".htmlspecialchars("forums.php?action=viewtopic&topicid=".$topicid."&page=p".$postid."#pid".$postid)."\">#".$postid."</a>&nbsp;&nbsp;<font color=\"gray\">".$lang_forums['text_by']."</font>".$by."&nbsp;&nbsp;<font color=\"gray\">".$lang_forums['text_at']."</font>".$added);
		if (is_valid_id($arr['editedby']))
			print("");
		print("&nbsp;&nbsp;<font color=\"gray\">|</font>&nbsp;&nbsp;");
		if ($authorid)
			print("<a href=\"?action=viewtopic&topicid=".$topicid."\">".$lang_forums['text_view_all_posts']."</a>");
		else
			print("<a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$topicid."&authorid=".$posterid)."\">".$lang_forums['text_view_this_author_only']."</a>");
		print("</td><td class=\"embedded nowrap\" width=\"1%\"><font class=\"big\">".$lang_forums['text_number']."<b>".($pn+$offset)."</b>".$lang_forums['text_lou']."&nbsp;&nbsp;</font><a href=\"#top\"><img class=\"top\" src=\"pic/trans.gif\" alt=\"Top\" title=\"".$lang_forums['text_back_to_top']."\" /></a>&nbsp;&nbsp;</td></tr>");

		print("</table></div>\n");

		print("<table class=\"main\" width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");

		$body = "<div id=\"pid".$postid."body\">";
        $bodyContent = format_comment($arr["body"]);
		if ($highlight){
            $bodyContent = highlight($highlight,$body);
		}

		if (is_valid_id($arr['editedby']))
		{
			$lastedittime = gettime($arr['editdate'],true,false);
            $bodyContent .= "<br /><p><font class=\"small\">".$lang_forums['text_last_edited_by'].get_username($arr['editedby']).$lang_forums['text_last_edit_at'].$lastedittime."</font></p>\n";
		}
		$bodyContent = apply_filter('post_body', $bodyContent, $arr, $allPosts);
		$body .= $bodyContent . "</div>";
		if ($signature)
		$body .= "<p style='vertical-align:bottom'><br />____________________<br />" . format_comment($signature,false,false,false,true,500,true,false, 1,200) . "</p>";

		$stats = "<br />"."&nbsp;&nbsp;".$lang_forums['text_posts']."$forumposts<br />"."&nbsp;&nbsp;".$lang_forums['text_ul']."$uploaded <br />"."&nbsp;&nbsp;".$lang_forums['text_dl']."$downloaded<br />"."&nbsp;&nbsp;".$lang_forums['text_ratio']."$ratio";
		print("<tr><td class=\"rowfollow\" width=\"150\" valign=\"top\" align=\"left\" style='padding: 0px'>" .
		return_avatar_image($avatar). "<br /><br /><br />&nbsp;&nbsp;<img alt=\"".get_user_class_name($arr2["class"],false,false,true)."\" title=\"".get_user_class_name($arr2["class"],false,false,true)."\" src=\"".$uclass."\" />".$stats."</td><td class=\"rowfollow\" valign=\"top\"><br />".$body."</td></tr>\n");
		$secs = 900;
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs))); // calculate date.
		print("<tr><td class=\"rowfollow\" align=\"center\" valign=\"middle\">".("'".$arr2['last_access']."'">$dt?"<img class=\"f_online\" src=\"pic/trans.gif\" alt=\"Online\" title=\"".$lang_forums['title_online']."\" />":"<img class=\"f_offline\" src=\"pic/trans.gif\" alt=\"Offline\" title=\"".$lang_forums['title_offline']."\" />" )."<a href=\"sendmessage.php?receiver=".htmlspecialchars(trim($arr2["id"]))."\"><img class=\"f_pm\" src=\"pic/trans.gif\" alt=\"PM\" title=\"".$lang_forums['title_send_message_to'].htmlspecialchars($arr2["username"])."\" /></a><a href=\"report.php?forumpost=$postid\"><img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"".$lang_forums['title_report_this_post']."\" /></a></td>");
		print("<td class=\"toolbox\" align=\"right\">");

		do_action('post_toolbox', $arr, $allPosts, $CURUSER['id']);

		if ($maypost)
		print("<a href=\"".htmlspecialchars("?action=quotepost&postid=".$postid)."\"><img class=\"f_quote\" src=\"pic/trans.gif\" alt=\"Quote\" title=\"".$lang_forums['title_reply_with_quote']."\" /></a>");

		if (get_user_class() >= $postmanage_class || $is_forummod)
		print("<a href=\"".htmlspecialchars("?action=deletepost&postid=".$postid)."\"><img class=\"f_delete\" src=\"pic/trans.gif\" alt=\"Delete\" title=\"".$lang_forums['title_delete_post']."\" /></a>");

		if (($CURUSER["id"] == $posterid && !$locked) || get_user_class() >= $postmanage_class || $is_forummod)
		print("<a href=\"".htmlspecialchars("?action=editpost&postid=".$postid)."\"><img class=\"f_edit\" src=\"pic/trans.gif\" alt=\"Edit\" title=\"".$lang_forums['title_edit_post']."\" /></a>");
		print("</td></tr></table>");
	}

	//------ Mod options

	if (get_user_class() >= $postmanage_class || $is_forummod)
	{
		print("</td></tr><tr><td class=\"toolbox\" align=\"center\">\n");
		print("<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"left\">\n");
		print("<tr><td class=\"embedded\"><form method=\"post\" action=\"?action=setsticky\">\n");
		print("<input type=\"hidden\" name=\"topicid\" value=\"".$topicid."\" />\n");
		print("<input type=\"hidden\" name=\"returnto\" value=\"".htmlspecialchars($_SERVER['REQUEST_URI'])."\" />\n");
		print("<input type=\"hidden\" name=\"sticky\" value=\"".($sticky ? 'no' : 'yes')."\" /><input type=\"submit\" class=\"medium\" value=\"".($sticky ? $lang_forums['submit_unsticky'] : $lang_forums['submit_sticky'])."\" /></form></td>\n");
		print("<td class=\"embedded\"><form method=\"post\" action=\"?action=setlocked\">\n");
		print("<input type=\"hidden\" name=\"topicid\" value=\"".$topicid."\" />\n");
		print("<input type=\"hidden\" name=\"returnto\" value=\"".htmlspecialchars($_SERVER['REQUEST_URI'])."\" />\n");
		print("<input type=\"hidden\" name=\"locked\" value=\"".($locked ? 'no' : 'yes')."\" /><input type=\"submit\" class=\"medium\" value=\"".($locked ? $lang_forums['submit_unlock'] : $lang_forums['submit_lock'])."\" /></form></td>\n");
		print("<td class=\"embedded\"><form method=\"get\" action=\"?\">\n");
		print("<input type=\"hidden\" name=\"action\" value=\"deletetopic\" />\n");
		print("<input type=\"hidden\" name=\"topicid\" value=\"".$topicid."\" />\n");
		print("<input type=\"hidden\" name=\"forumid\" value=\"".$forumid."\" />\n");
		print("<input type=\"submit\" class=\"medium\" value=\"".$lang_forums['submit_delete_topic']."\" /></form></td>\n");
		print("<td class=\"embedded\"><form method=\"post\" action=\"".htmlspecialchars("?action=movetopic&topicid=".$topicid)."\">\n"."&nbsp;".$lang_forums['text_move_thread_to']."&nbsp;<select class=\"med\" name=\"forumid\">");
		$forums = get_forum_row();
		foreach ($forums as $arr){
			if ($arr["id"] != $forumid && get_user_class() >= $arr["minclasswrite"])
				print("<option value=\"" . $arr["id"] . "\">" . htmlspecialchars($arr["name"]) . "</option>\n");
		}
		print("</select> <input type=\"submit\" class=\"medium\" value=\"".$lang_forums['submit_move']."\" /></form></td>");
		print("<td class=\"embedded\"><form method=\"post\" action=\"".htmlspecialchars("?action=hltopic&topicid=".$topicid)."\">\n"."&nbsp;".$lang_forums['text_highlight_topic']."&nbsp;<select class=\"med\" name=\"color\">");
		print("<option value='0'>".$lang_forums['select_color']."</option>
<option style='background-color: black' value=\"1\">Black</option>
<option style='background-color: sienna' value=\"2\">Sienna</option>
<option style='background-color: darkolivegreen' value=\"3\">Dark Olive Green</option>
<option style='background-color: darkgreen' value=\"4\">Dark Green</option>
<option style='background-color: darkslateblue' value=\"5\">Dark Slate Blue</option>
<option style='background-color: navy' value=\"6\">Navy</option>
<option style='background-color: indigo' value=\"7\">Indigo</option>
<option style='background-color: darkslategray' value=\"8\">Dark Slate Gray</option>
<option style='background-color: darkred' value=\"9\">Dark Red</option>
<option style='background-color: darkorange' value=\"10\">Dark Orange</option>
<option style='background-color: olive' value=\"11\">Olive</option>
<option style='background-color: green' value=\"12\">Green</option>
<option style='background-color: teal' value=\"13\">Teal</option>
<option style='background-color: blue' value=\"14\">Blue</option>
<option style='background-color: slategray' value=\"15\">Slate Gray</option>
<option style='background-color: dimgray' value=\"16\">Dim Gray</option>
<option style='background-color: red' value=\"17\">Red</option>
<option style='background-color: sandybrown' value=\"18\">Sandy Brown</option>
<option style='background-color: yellowgreen' value=\"19\">Yellow Green</option>
<option style='background-color: seagreen' value=\"20\">Sea Green</option>
<option style='background-color: mediumturquoise' value=\"21\">Medium Turquoise</option>
<option style='background-color: royalblue' value=\"22\">Royal Blue</option>
<option style='background-color: purple' value=\"23\">Purple</option>
<option style='background-color: gray' value=\"24\">Gray</option>
<option style='background-color: magenta' value=\"25\">Magenta</option>
<option style='background-color: orange' value=\"26\">Orange</option>
<option style='background-color: yellow' value=\"27\">Yellow</option>
<option style='background-color: lime' value=\"28\">Lime</option>
<option style='background-color: cyan' value=\"29\">Cyan</option>
<option style='background-color: deepskyblue' value=\"30\">Deep Sky Blue</option>
<option style='background-color: darkorchid' value=\"31\">Dark Orchid</option>
<option style='background-color: silver' value=\"32\">Silver</option>
<option style='background-color: pink' value=\"33\">Pink</option>
<option style='background-color: wheat' value=\"34\">Wheat</option>
<option style='background-color: lemonchiffon' value=\"35\">Lemon Chiffon</option>
<option style='background-color: palegreen' value=\"36\">Pale Green</option>
<option style='background-color: paleturquoise' value=\"37\">Pale Turquoise</option>
<option style='background-color: lightblue' value=\"38\">Light Blue</option>
<option style='background-color: plum' value=\"39\">Plum</option>
<option style='background-color: white' value=\"40\">White</option>");
		print("</select>");
		print("<input type=\"hidden\" name=\"returnto\" value=\"".htmlspecialchars($_SERVER['REQUEST_URI'])."\" />\n");
		print("<input type=\"submit\" class=\"medium\" value=\"".$lang_forums['submit_change']."\" /></form></td>");
		print("</tr>\n");
		print("</table>\n");
	}

	end_frame();

	end_main_frame();

	print($pagerbottom);
	if ($maypost){
	print("<br /><table style='border:1px solid #000000;'><tr>".
"<td class=\"text\" align=\"center\"><b>".$lang_forums['text_quick_reply']."</b><br /><br />".
"<form id=\"compose\" name=\"compose\" method=\"post\" action=\"?action=post\" onsubmit=\"return postvalid(this);\">".
"<input type=\"hidden\" name=\"id\" value=\"".$topicid."\" /><input type=\"hidden\" name=\"type\" value=\"reply\" /><br />");
	quickreply('compose', 'body',$lang_forums['submit_add_reply']);
	print("</form></td></tr></table>");
	print("<p align=\"center\"><a class=\"index\" href=\"".htmlspecialchars("?action=reply&topicid=".$topicid)."\">".$lang_forums['text_add_reply']."</a></p>\n");
	}
	elseif ($locked)
		print($lang_forums['text_topic_locked_new_denied']);
	else print($lang_forums['text_unpermitted_posting_here']);

	print(key_shortcut($page,$pages-1));
    do_action('page_forums_js');
	stdfoot();
	die;
}

//-------- Action: Move topic

if ($action == "movetopic")
{
	$forumid = intval($_POST["forumid"] ?? 0);

	$topicid = intval($_GET["topicid"] ?? 0);
	$ismod = is_forum_moderator($topicid,'topic');
	if (!is_valid_id($forumid) || !is_valid_id($topicid) || (get_user_class() < $postmanage_class && !$ismod))
		permissiondenied();

	// Make sure topic and forum is valid

	$res = @sql_query("SELECT minclasswrite FROM forums WHERE id=$forumid") or sqlerr(__FILE__, __LINE__);

	if (mysql_num_rows($res) != 1)
	stderr($lang_forums['std_error'], $lang_forums['std_forum_not_found']);

	$arr = mysql_fetch_row($res);

	if (get_user_class() < $arr[0])
		permissiondenied();

	$res = @sql_query("SELECT forumid FROM topics WHERE id=$topicid") or sqlerr(__FILE__, __LINE__);
	if (mysql_num_rows($res) != 1)
		stderr($lang_forums['std_error'], $lang_forums['std_topic_not_found']);
	$arr = mysql_fetch_row($res);
	$old_forumid=$arr[0];

	// get posts count
	$res = sql_query("SELECT COUNT(id) AS nb_posts FROM posts WHERE topicid=$topicid") or sqlerr(__FILE__, __LINE__);
	if (mysql_num_rows($res) != 1)
	stderr($lang_forums['std_error'], $lang_forums['std_cannot_get_posts_count']);
	$arr = mysql_fetch_row($res);
	$nb_posts = $arr[0];

	// move topic
	if ($old_forumid != $forumid)
	{
		@sql_query("UPDATE topics SET forumid=$forumid WHERE id=$topicid") or sqlerr(__FILE__, __LINE__);
		// update counts
		@sql_query("UPDATE forums SET topiccount=topiccount-1, postcount=postcount-$nb_posts WHERE id=$old_forumid") or sqlerr(__FILE__, __LINE__);
		$Cache->delete_value('forum_'.$old_forumid.'_post_'.$today_date.'_count');
		$Cache->delete_value('forum_'.$old_forumid.'_last_replied_topic_content');
		@sql_query("UPDATE forums SET topiccount=topiccount+1, postcount=postcount+$nb_posts WHERE id=$forumid") or sqlerr(__FILE__, __LINE__);
		$Cache->delete_value('forum_'.$forumid.'_post_'.$today_date.'_count');
		$Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
	}

	// Redirect to forum page

	header("Location: " . get_protocol_prefix() . "$BASEURL/forums.php?action=viewforum&forumid=$forumid");

	die;
}

//-------- Action: Delete topic

if ($action == "deletetopic")
{
	$topicid = intval($_GET["topicid"] ?? 0);
	$res1 = sql_query("SELECT forumid, userid FROM topics WHERE id=".sqlesc($topicid)." LIMIT 1") or sqlerr(__FILE__, __LINE__);
	$row1 = mysql_fetch_array($res1);
	if (!$row1){
		die;
	}
	else {
		$forumid = $row1['forumid'];
		$userid = $row1['userid'];
	}
	$ismod = is_forum_moderator($topicid,'topic');
	if (!is_valid_id($topicid) || (get_user_class() < $postmanage_class && !$ismod))
		permissiondenied();

	$sure = intval($_GET["sure"] ?? 0);
	if (!$sure)
	{
		stderr($lang_forums['std_delete_topic'], $lang_forums['std_delete_topic_note'] .
		"<a class=altlink href=?action=deletetopic&topicid=$topicid&sure=1>".$lang_forums['std_here_if_sure'],false);
	}

	$postcount = get_row_count("posts","WHERE topicid=".sqlesc($topicid));

	sql_query("DELETE FROM topics WHERE id=$topicid") or sqlerr(__FILE__, __LINE__);
	sql_query("DELETE FROM posts WHERE topicid=$topicid") or sqlerr(__FILE__, __LINE__);
	sql_query("DELETE FROM readposts WHERE topicid=$topicid") or sqlerr(__FILE__, __LINE__);
	@sql_query("UPDATE forums SET topiccount=topiccount-1, postcount=postcount-$postcount WHERE id=".sqlesc($forumid)) or sqlerr(__FILE__, __LINE__);
	$Cache->delete_value('forum_'.$forumid.'_post_'.$today_date.'_count');
	$forum_last_replied_topic_row = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content');
	if ($forum_last_replied_topic_row && $forum_last_replied_topic_row['id'] == $topicid)
		$Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');

	//===remove karma
	KPS("-",$starttopic_bonus,$userid);
	//===end

	header("Location: " . get_protocol_prefix() . "$BASEURL/forums.php?action=viewforum&forumid=$forumid");
	die;
}

//-------- Action: Delete post

if ($action == "deletepost")
{
	$postid = intval($_GET["postid"] ?? 0);
	$sure = intval($_GET["sure"] ?? 0);

	$ismod = is_forum_moderator($postid, 'post');
	if ((get_user_class() < $postmanage_class && !$ismod) || !is_valid_id($postid))
		permissiondenied();

	//------- Get topic id
	$res = sql_query("SELECT topicid, userid FROM posts WHERE id=$postid") or sqlerr(__FILE__, __LINE__);
	$arr = mysql_fetch_array($res) or stderr($lang_forums['std_error'], $lang_forums['std_post_not_found']);
	$topicid = $arr['topicid'];
	$userid = $arr['userid'];

	//------- Get the id of the last post before the one we're deleting
	$res = sql_query("SELECT id FROM posts WHERE topicid=$topicid AND id < $postid ORDER BY id DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
	if (mysql_num_rows($res) == 0) // This is the first post of a topic
		stderr($lang_forums['std_error'], $lang_forums['std_cannot_delete_post'] .
	"<a class=altlink href=?action=deletetopic&topicid=$topicid&sure=1>".$lang_forums['std_delete_topic_instead'],false);
	else
	{
		$arr = mysql_fetch_row($res);
		$redirtopost = "&page=p$arr[0]#pid$arr[0]";
	}

	//------- Make sure we know what we do :-)
	if (!$sure)
	{
		stderr($lang_forums['std_delete_post'], $lang_forums['std_delete_post_note'] .
		"<a class=altlink href=?action=deletepost&postid=$postid&sure=1>".$lang_forums['std_here_if_sure'],false);
	}

	//------- Delete post
	sql_query("DELETE FROM posts WHERE id=$postid") or sqlerr(__FILE__, __LINE__);
	$Cache->delete_value('user_'.$userid.'_post_count');
	$Cache->delete_value('topic_'.$topicid.'_post_count');
	// update forum
	$forumid = get_single_value("topics","forumid","WHERE id=".sqlesc($topicid));
	if (!$forumid)
		die();
	else{
		sql_query("UPDATE forums SET postcount=postcount-1 WHERE id=".sqlesc($forumid));
	}
	$forum_last_replied_topic_row = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content');
	if ($forum_last_replied_topic_row && $forum_last_replied_topic_row['lastpost'] == $postid)
		$Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
	//------- Update topic
	update_topic_last_post($topicid);

	//===remove karma
	KPS("-",$makepost_bonus,$userid);

	header("Location: " . get_protocol_prefix() . "$BASEURL/forums.php?action=viewtopic&topicid=$topicid$redirtopost");
	die;
}

//-------- Action: Set locked on/off

if ($action == "setlocked")
{
	$topicid = intval($_POST["topicid"] ?? 0);
	$ismod = is_forum_moderator($topicid,'topic');
	if (!$topicid || (get_user_class() < $postmanage_class && !$ismod))
		permissiondenied();

	$locked = sqlesc($_POST["locked"]);
	sql_query("UPDATE topics SET locked=$locked WHERE id=$topicid") or sqlerr(__FILE__, __LINE__);

	header("Location: $_POST[returnto]");
	die;
}

if ($action == 'hltopic')
{
	$topicid = intval($_GET["topicid"] ?? 0);
	$ismod = is_forum_moderator($topicid,'topic');
	if (!$topicid || (get_user_class() < $postmanage_class && !$ismod))
		permissiondenied();
	$color = $_POST["color"];
	if ($color==0 || get_hl_color($color))
		sql_query("UPDATE topics SET hlcolor=".sqlesc($color)." WHERE id=".sqlesc($topicid)) or sqlerr(__FILE__, __LINE__);

	$forumid = get_single_value("topics","forumid","WHERE id=".sqlesc($topicid));
	$forum_last_replied_topic_row = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content');
	if ($forum_last_replied_topic_row && $forum_last_replied_topic_row['id'] == $topicid)
		$Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
	header("Location: $_POST[returnto]");
	die;
}

//-------- Action: Set sticky on/off

if ($action == "setsticky")
{
	$topicid = intval($_POST["topicid"] ?? 0);
	$ismod = is_forum_moderator($topicid,'topic');
	if (!$topicid || (get_user_class() < $postmanage_class && !$ismod))
		permissiondenied();

	$sticky = sqlesc($_POST["sticky"]);
	sql_query("UPDATE topics SET sticky=$sticky WHERE id=$topicid") or sqlerr(__FILE__, __LINE__);

	header("Location: $_POST[returnto]");
	die;
}

//-------- Action: View forum

if ($action == "viewforum")
{
	$forumid = intval($_GET["forumid"] ?? 0);
	int_check($forumid,true);
	$userid = intval($CURUSER["id"] ?? 0);
	//------ Get forum name, moderators
	$row = get_forum_row($forumid);
	if (!$row){
		write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is trying to visit forum that doesn't exist", 'mod');
		stderr($lang_forums['std_forum_error'],$lang_forums['std_forum_not_found']);
	}
	if (get_user_class() < $row["minclassread"])
		permissiondenied();

	$forumname = $row['name'];
	$forummoderators = get_forum_moderators($forumid,false);
	$search = mysql_real_escape_string(trim($_GET["search"] ?? ''));
	if ($search){
		$wherea = " AND subject LIKE '%$search%'";
		$addparam .= "&search=".rawurlencode($search);
	}
	else{
		$wherea = "";
		$addparam = "";
	}
	$num = get_row_count("topics","WHERE forumid=".sqlesc($forumid).$wherea);

	list($pagertop, $pagerbottom, $limit) = pager($topicsperpage, $num, "?"."action=viewforum&forumid=".$forumid.$addparam."&");
	if (isset($_GET["sort"])){
		switch ($_GET["sort"]){
			case 'firstpostasc':
			{
				$orderby = "firstpost ASC";
				break;
			}
			case 'firstpostdesc':
			{
				$orderby = "firstpost DESC";
				break;
			}
			case 'lastpostasc':
			{
				$orderby = "lastpost ASC";
				break;
			}
			case 'lastpostdesc':
			{
				$orderby = "lastpost DESC";
				break;
			}
			default:
			{
				$orderby = "lastpost DESC";
			}
		}
	}
	else
	{
		$orderby = "lastpost DESC";
	}
	//------ Get topics data
	$topicsres = sql_query("SELECT * FROM topics WHERE forumid=".sqlesc($forumid).$wherea." ORDER BY sticky DESC,".$orderby." ".$limit) or sqlerr(__FILE__, __LINE__);
	$numtopics = mysql_num_rows($topicsres);
	stdhead($lang_forums['head_forum']." ".$forumname);
	begin_main_frame("",true);
	print("<h1 align=\"center\"><a class=\"faqlink\" href=\"forums.php\">".$SITENAME."&nbsp;".$lang_forums['text_forums'] ."</a>--><a class=\"faqlink\" href=\"".htmlspecialchars("forums.php?action=viewforum&forumid=".$forumid)."\">".$forumname."</a></h1>\n");
	end_main_frame();
	print("<br />");
	$maypost = get_user_class() >= $row["minclasswrite"] && get_user_class() >= $row["minclasscreate"] && $CURUSER["forumpost"] == 'yes';

	if (!$maypost)
		print("<p><i>".$lang_forums['text_unpermitted_starting_new_topics']."</i></p>\n");

	print("<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\"><tr>\n");
	print("<td class=\"embedded\" width=\"90%\">");
	print($forummoderators ? "&nbsp;&nbsp;<img class=\"forum_mod\" src=\"pic/trans.gif\" alt=\"Moderator\" title=\"".$lang_forums['col_moderator']."\">&nbsp;".$forummoderators : "");
	print("</td><td class=\"embedded nowrap\" width=\"1%\">");
	if ($maypost)
		print("<a href=\"".htmlspecialchars("?action=newtopic&forumid=".$forumid)."\"><img class=\"f_new\" src=\"pic/trans.gif\" alt=\"New Topic\" title=\"".$lang_forums['title_new_topic']."\" /></a>&nbsp;&nbsp;");
	print("</td>");
	print("</tr></table>\n");
	if ($numtopics > 0)
	{
		print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">");

		print("<tr><td class=\"colhead\" align=\"center\" width=\"99%\">".$lang_forums['col_topic']."</td><td class=\"colhead\" align=\"center\"><a href=\"".htmlspecialchars("?action=viewforum&forumid=".$forumid.$addparam."&sort=".(isset($_GET["sort"]) && $_GET["sort"] == 'firstpostdesc' ? "firstpostasc" : "firstpostdesc"))."\" title=\"".(isset($_GET["sort"]) && $_GET["sort"] == 'firstpostdesc' ?  $lang_forums['title_order_topic_asc'] : $lang_forums['title_order_topic_desc'])."\">".$lang_forums['col_author']."</a></td><td class=\"colhead\" align=\"center\">".$lang_forums['col_replies']."/".$lang_forums['col_views']."</td><td class=\"colhead\" align=\"center\"><a href=\"".htmlspecialchars("?action=viewforum&forumid=".$forumid.$addparam."&sort=".(isset($_GET["sort"]) && $_GET["sort"] == 'lastpostasc' ? "lastpostdesc" : "lastpostasc"))."\" title=\"".(isset($_GET["sort"]) && $_GET["sort"] == 'lastpostasc' ? $lang_forums['title_order_post_desc'] : $lang_forums['title_order_post_asc'])."\">".$lang_forums['col_last_post']."</a></td>\n");

		print("</tr>\n");
		$counter = 0;

		while ($topicarr = mysql_fetch_assoc($topicsres))
		{
			$topicid = $topicarr["id"];

			$topic_userid = $topicarr["userid"];

			$topic_views = $topicarr["views"];

			$views = number_format($topic_views);

			$locked = $topicarr["locked"] == "yes";

			$sticky = $topicarr["sticky"] == "yes";

			$hlcolor = $topicarr["hlcolor"];

			//---- Get reply count
			if (!$posts = $Cache->get_value('topic_'.$topicid.'_post_count')){
				$posts = get_row_count("posts","WHERE topicid=".sqlesc($topicid));
				$Cache->cache_value('topic_'.$topicid.'_post_count', $posts, 3600);
			}

			$replies = max(0, $posts - 1);

			$tpages = floor($posts / $postsperpage);

			if ($tpages * $postsperpage != $posts)
			++$tpages;

			if ($tpages > 1)
			{
				$topicpages = " [<img class=\"multipage\" src=\"pic/trans.gif\" alt=\"multi-page\" /> ";
				$dotted = 0;
				$dotspace = 4;
				$dotend = $tpages - $dotspace;
				for ($i = 1; $i <= $tpages; ++$i){
					if ($i > $dotspace && $i <= $dotend) {
						if (!$dotted)
						$topicpages .= " ... ";
						$dotted = 1;
						continue;
					}
				$topicpages .= " <a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$topicid."&page=".($i-1))."\">$i</a>";
				}

				$topicpages .= " ]";
			}
			else
			$topicpages = "";

			//---- Get userID and date of last post

			$arr = get_post_row($topicarr['lastpost']);
			$lppostid = intval($arr["id"] ?? 0);
			$lpuserid = intval($arr["userid"] ?? 0);
			$lpusername = get_username($lpuserid);
			$lpadded = gettime($arr["added"],true,false);
			$onmouseover = "";
			if ($enabletooltip_tweak == 'yes' && $CURUSER['showlastpost'] != 'no'){
				if ($CURUSER['timetype'] != 'timealive')
					$lastposttime = $lang_forums['text_at_time'].$arr["added"];
				else
					$lastposttime = $lang_forums['text_blank'].gettime($arr["added"],true,false,true);
				$lptext = format_comment(mb_substr($arr['body'],0,100,"UTF-8") . (mb_strlen($arr['body'],"UTF-8") > 100 ? " ......" : "" ),true,false,false,true,600,false,false);
				$lastpost_tooltip[$counter]['id'] = "lastpost_" . $counter;
				$lastpost_tooltip[$counter]['content'] = $lang_forums['text_last_posted_by'].$lpusername.$lastposttime."<br />".$lptext;
				$onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('" . $lastpost_tooltip[$counter]['id'] . "'), 'trail', false,'lifetime', 5000,'styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
			}

			$arr = get_post_row($topicarr['firstpost']);
			$fpuserid = intval($arr["userid"] ?? 0);
			$fpauthor = get_username($arr["userid"]);

			$subject = ($sticky ? "<img class=\"sticky\" src=\"pic/trans.gif\" alt=\"Sticky\" title=\"".$lang_forums['title_sticky']."\" />&nbsp;&nbsp;" : "") . "<a href=\"".htmlspecialchars("?action=viewtopic&forumid=".$forumid."&topicid=".$topicid)."\" ".$onmouseover.">" .highlight_topic(highlight($search,htmlspecialchars($topicarr["subject"])), $hlcolor) . "</a>".$topicpages;
			$lastpostread = get_last_read_post_id($topicid);

			if ($lastpostread >= $lppostid)
				$img = get_topic_image($locked ? "locked" : "read");
			else{
				$img = get_topic_image($locked ? "lockednew" : "unread");
				if ($lastpostread != $CURUSER['last_catchup'])
					$subject .= "&nbsp;&nbsp;<a href=\"".htmlspecialchars("?action=viewtopic&forumid=".$forumid."&topicid=".$topicid."&page=p".$lastpostread."#pid".$lastpostread)."\" title=\"".$lang_forums['title_jump_to_unread']."\"><font class=\"small new\"><b>".$lang_forums['text_new']."</b></font></a>";
			}


			$topictime = substr($arr['added'],0,10);
			if (strtotime($arr['added']) +  86400 > TIMENOW)
				$topictime = "<font class=\"new small\">".$topictime."</font>";
			else
				$topictime = "<font color=\"gray\" class=\"small\">".$topictime."</font>";

			print("<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr>" .
			"<td class=\"embedded\" style='padding-right: 10px'>".$img .
			"</td><td class=\"embedded\" align=\"left\">\n" .
			$subject."</td></tr></table></td><td class=\"rowfollow\" align=\"center\">".get_username($fpuserid)."<br />".$topictime."</td><td class=\"rowfollow\" align=\"center\">".$replies." / <font color=\"gray\">".$views."</font></td>\n" .
			"<td class=\"rowfollow nowrap\" align=\"center\">".$lpadded."<br />".$lpusername."</td>\n");

			print("</tr>\n");
			$counter++;

		} // while

		//print("</table>\n");
		//print("<table border=\"0\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">");
		print("<tr><td align=\"left\">\n");
		print("<form method=\"get\" action=\"forums.php\"><b>".$lang_forums['text_fast_search']."</b><input type=\"hidden\" name=\"action\" value=\"viewforum\" /><input type=\"hidden\" name=\"forumid\" value=\"".$forumid."\" /><input type=\"text\" style=\"width: 180px\" name=\"search\" />&nbsp;<input type=\"submit\" value=\"".$lang_forums['text_go']."\" /></form>");
		print("</td>");
?>
<td align="left" colspan="3">
<span id="order" onclick="dropmenu(this);"><span style="cursor: pointer;"><b><?php echo $lang_forums['text_order']?></b></span>
<span id="orderlist" class="dropmenu" style="display: none"><ul>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=firstpostdesc"><?php echo $lang_forums['text_topic_desc']?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=firstpostasc"><?php echo $lang_forums['text_topic_asc']?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=lastpostdesc"><?php echo $lang_forums['text_post_desc']?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=lastpostasc"><?php echo $lang_forums['text_post_asc']?></a></li>
</ul>
</span>
</span>
</td>
<?php
		print("</tr></table>");
		print($pagerbottom);
		if ($enabletooltip_tweak == 'yes' && $CURUSER['showlastpost'] != 'no')
			create_tooltip_container($lastpost_tooltip, 400);
	} // if
	else
		print("<p>".$lang_forums['text_no_topics_found']."</p>");
	stdfoot();
	die;
}

//-------- Action: View unread posts

if ($action == "viewunread")
{
	$userid = $CURUSER['id'];

	$beforepostid = intval($_GET['beforepostid'] ?? 0);
	$maxresults = 25;
	$res = sql_query("SELECT id, forumid, subject, lastpost, hlcolor FROM topics WHERE lastpost > ".$CURUSER['last_catchup'].($beforepostid ? " AND lastpost < ".sqlesc($beforepostid) : "")." ORDER BY lastpost DESC LIMIT 100") or sqlerr(__FILE__, __LINE__);

	stdhead($lang_forums['head_view_unread']);
	print("<h1 align=\"center\"><a class=\"faqlink\" href=\"forums.php\">".$SITENAME."&nbsp;".$lang_forums['text_forums']."</a>-->".$lang_forums['text_topics_with_unread_posts']."</h1>");

	$n = 0;
	$uc = get_user_class();

	while ($arr = mysql_fetch_assoc($res))
	{
		$topiclastpost = $arr['lastpost'];
		$topicid = $arr['id'];

		//---- Check if post is read
		$lastpostread = get_last_read_post_id($topicid);

		if ($lastpostread >= $topiclastpost)
			continue;

		$forumid = $arr['forumid'];
		//---- Check access & get forum name
		$a = get_forum_row($forumid);
		if ($uc < $a['minclassread'])
			continue;
		++$n;
		if ($n > $maxresults)
			break;

		$forumname = $a['name'];
		if ($n == 1)
		{
			print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
			print("<tr><td class=\"colhead\" align=\"left\">".$lang_forums['col_topic']."</td><td class=\"colhead\" align=\"left\">".$lang_forums['col_forum']."</td></tr>\n");
		}
		print("<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\" style='padding-right: 10px'>" .
		get_topic_image("unread")."</td><td class=\"embedded\">" .
		"<a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$topicid.($lastpostread > 0 && $lastpostread != $CURUSER['last_catchup'] ? "&page=p".$lastpostread."#pid".$lastpostread : ""))."\">" . highlight_topic(htmlspecialchars($arr["subject"]), $arr["hlcolor"]).
		"</a></td></tr></table></td><td class=\"rowfollow\" align=\"left\"><a href=\"".htmlspecialchars("?action=viewforum&forumid=".$forumid)."\"><b>".$forumname."</b></a></td></tr>\n");
	}
	if ($n > 0)
	{
		print("</table>\n");
		print("<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"1%\"><tr><td class=\"embedded\"><form method=\"get\" action=\"?\"><input type=\"hidden\" name=\"catchup\" value=\"1\" /><input type=\"submit\" value=\"".$lang_forums['text_catch_up']."\" class=\"btn\" /></form></td>");
		if ($n > $maxresults){
			print("<td class=\"embedded\"><form method=\"get\" action=\"?\"><input type=\"hidden\" name=\"action\" value=\"viewunread\" /><input type=\"hidden\" name=\"beforepostid\" value=\"".$topiclastpost."\" /><input type=\"submit\" value=\"".$lang_forums['submit_show_more']."\" class=\"btn\" /></form></td>");
		}
		print("</tr></table>");
	}
	else
		print("<p>".$lang_forums['text_nothing_found']."</p>");
	stdfoot();
	die;
}

if ($action == "search")
{
	stdhead($lang_forums['head_forum_search']);
	unset($error);
	$error = true;
	$found = "";
	$keywords = htmlspecialchars(trim($_GET["keywords"]));
	if ($keywords != "")
	{
		$extraSql 	= " LIKE '%".mysql_real_escape_string($keywords)."%'";

		$res = sql_query("SELECT COUNT(posts.id) FROM posts LEFT JOIN topics ON posts.topicid = topics.id LEFT JOIN forums ON topics.forumid = forums.id WHERE forums.minclassread <= ".sqlesc(get_user_class())." AND ((topics.subject $extraSql AND posts.id=topics.firstpost) OR posts.body $extraSql)") or sqlerr(__FILE__, __LINE__);
		$arr = mysql_fetch_row($res);
		$hits = intval($arr[0] ?? 0);
		if ($hits){
			$error = false;
			$found = "[<b><font class=\"striking\"> ".$lang_forums['text_found'].$hits.$lang_forums['text_num_posts']." </font></b>]";
		}
	}
?>
<style type="text/css">
.search{
	background-image:url(pic/search.gif);
	background-repeat:no-repeat;
	width:579px;
	height:95px;
	margin:5px 0 5px 0;
	text-align:left;
}
.search_title{
	color:#0062AE;
	background-color:#DAF3FB;
	font-size:12px;
	font-weight:bold;
	text-align:left;
	padding:7px 0 0 15px;
}

.search_table {
	border-collapse: collapse;
	border: none;
	background-color: #ffffff;
}

</style>
<div class="search">
	<div class="search_title"><?php echo $lang_forums['text_search_on_forum'] ?> <?php echo ($error && $keywords != "" ? "[<b><font color=striking> ".$lang_forums['text_nothing_found']."</font></b> ]" : $found)?></div>
	<div style="margin-left: 53px; margin-top: 13px;">
		<form method="get" action="forums.php" id="search_form" style="margin: 0pt; padding: 0pt; font-family: Tahoma,Arial,Helvetica,sans-serif; font-size: 11px;">
		<input type="hidden" name="action" value="search" />
		<table border="0" cellpadding="0" cellspacing="0" width="512" class="search_table">
		<tbody>
		<tr>
		<td style="padding-bottom: 3px; border: 0;" valign="top"><?php echo $lang_forums['text_by_keyword'] ?></td>
		</tr>
		<tr>
		<td style="padding-bottom: 3px; border: 0;" valign="top">
			<input name="keywords" type="text" value="<?php echo $keywords?>" style="width: 400px;" /></td>
			<td style="padding-bottom: 3px; border: 0;" valign="top"><input name="image" type="image" style="vertical-align: middle; padding-bottom: 0px; margin-left: 0px;" src="<?php echo get_forum_pic_folder()?>/search_button.gif" alt="Search" /></td>
		</tr>
		</tbody>
		</table>
		</form>
	</div>
</div>
<?php

	if (!$error)
	{
		$perpage = $topicsperpage;
		list($pagertop, $pagerbottom, $limit) = pager($perpage, $hits, "forums.php?action=search&keywords=".rawurlencode($keywords)."&");
		$res = sql_query("SELECT posts.id, posts.topicid, posts.userid, posts.added, topics.subject, topics.hlcolor, forums.id AS forumid, forums.name AS forumname FROM posts LEFT JOIN topics ON posts.topicid = topics.id LEFT JOIN forums ON topics.forumid = forums.id WHERE forums.minclassread <= ".sqlesc(get_user_class())." AND ((topics.subject $extraSql AND posts.id=topics.firstpost) OR posts.body $extraSql) ORDER BY posts.id DESC $limit") or sqlerr(__FILE__, __LINE__);

		print($pagertop);
		print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">\n");
		print("<tr><td class=\"colhead\" align=\"center\">".$lang_forums['col_post']."</td><td class=\"colhead\" align=\"center\" width=\"70%\">".$lang_forums['col_topic']."</td><td class=\"colhead\" align=\"left\">".$lang_forums['col_forum']."</td><td class=\"colhead\" align=\"left\">".$lang_forums['col_posted_by']."</td></tr>\n");

		while ($post = mysql_fetch_array($res))
		{
			print("<tr><td class=\"rowfollow\" align=\"center\" width=\"1%\">".$post['id']."</td><td class=\"rowfollow\" align=\"left\"><a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$post['topicid']."&highlight=".rawurlencode($keywords)."&page=p".$post['id']."#pid".$post['id'])."\">" . highlight_topic(highlight($keywords,htmlspecialchars($post['subject'])), $post['hlcolor']) . "</a></td><td class=\"rowfollow nowrap\" align=\"left\"><a href=\"".htmlspecialchars("?action=viewforum&forumid=".$post['forumid'])."\"><b>" . htmlspecialchars($post["forumname"]) . "</b></a></td><td class=\"rowfollow nowrap\" align=\"left\">" . gettime($post['added'],true,false) . "&nbsp;|&nbsp;". get_username($post['userid']) ."</td></tr>\n");
		}

		print("</table>\n");
		print($pagerbottom);
	}
stdfoot();
die;
}

if (isset($_GET["catchup"]) && $_GET["catchup"] == 1){
	catch_up();
}

//-------- Handle unknown action
if ($action != "")
	stderr($lang_forums['std_forum_error'], $lang_forums['std_unknown_action']);

//-------- Default action: View forums

//-------- Get forums
if ($CURUSER)
	$USERUPDATESET[] = "forum_access = ".sqlesc(date("Y-m-d H:i:s"));

stdhead($lang_forums['head_forums']);
begin_main_frame();
print("<h1 align=\"center\">".$SITENAME."&nbsp;".$lang_forums['text_forums']."</h1>");
print("<p align=\"center\"><a href=\"?action=search\"><b>".$lang_forums['text_search']."</b></a> | <a href=\"?action=viewunread\"><b>".$lang_forums['text_view_unread']."</b></a> | <a href=\"?catchup=1\"><b>".$lang_forums['text_catch_up']."</b></a> ".(get_user_class() >= $forummanage_class ? "| <a href=\"forummanage.php\"><b>".$lang_forums['text_forum_manager']."</b></a>":"")."</p>");
print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\">\n");

if (!$overforums = $Cache->get_value('overforums_list')){
	$overforums = array();
	$res = sql_query("SELECT * FROM overforums ORDER BY sort ASC") or sqlerr(__FILE__, __LINE__);
	while ($row = mysql_fetch_array($res))
		$overforums[] = $row;
	$Cache->cache_value('overforums_list', $overforums, 86400);
}
$count=0;
if ($Advertisement->enable_ad())
	$interoverforumsad=$Advertisement->get_ad('interoverforums');

foreach ($overforums as $a)
{
	if (get_user_class() < $a["minclassview"])
		continue;
	if ($count>=1)
	if ($Advertisement->enable_ad()){
		if (!empty($interoverforumsad[$count-1]))
			echo "<tr><td colspan=\"5\" align=\"center\" id=\"\">".$interoverforumsad[$count-1]."</td></tr>";
	}
	$forid = $a["id"];
	$overforumname = $a["name"];

	print("<tr><td align=\"left\" class=\"colhead\" width=\"99%\">".htmlspecialchars($overforumname)."</td><td align=\"center\" class=\"colhead\">".$lang_forums['col_topics']."</td>" .
	"<td align=\"center\" class=\"colhead\">".$lang_forums['col_posts']."</td>" .
	"<td align=\"left\" class=\"colhead\">".$lang_forums['col_last_post']."</td><td class=\"colhead\" align=\"left\">".$lang_forums['col_moderator']."</td></tr>\n");

	$forums = get_forum_row();
	foreach ($forums as $forums_arr)
	{
		if ($forums_arr['forid'] != $forid)
			continue;
		if (get_user_class() < $forums_arr["minclassread"])
			continue;

		$forumid = $forums_arr["id"];
		$forumname = htmlspecialchars($forums_arr["name"]);
		$forumdescription = htmlspecialchars($forums_arr["description"]);

		$forummoderators = get_forum_moderators($forums_arr['id'],false);
		if (!$forummoderators)
			$forummoderators = "<a href=\"contactstaff.php\"><i>".$lang_forums['text_apply_now']."</i></a>";

		$topiccount = number_format($forums_arr["topiccount"]);
		$postcount = number_format($forums_arr["postcount"]);

		// Find last post ID
		//Returns the ID of the last post of a forum
		if (!$arr = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content')){
			$res = sql_query("SELECT * FROM topics WHERE forumid=".sqlesc($forumid)." ORDER BY lastpost DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
			$arr = mysql_fetch_array($res);
			$Cache->cache_value('forum_'.$forumid.'_last_replied_topic_content', $arr, 900);
		}

		if ($arr)
		{
			$lastpostid = $arr['lastpost'];
			// Get last post info
			$post_arr = get_post_row($lastpostid);
			$lastposterid = $post_arr["userid"];
			$lastpostdate = gettime($post_arr["added"],true,false);
			$lasttopicid = $arr['id'];
			$hlcolor = $arr['hlcolor'];
			$lasttopicdissubject = $lasttopicsubject = $arr['subject'];
			$max_length_of_topic_subject = 35;
			$count_dispname = mb_strlen($lasttopicdissubject,"UTF-8");
			if ($count_dispname > $max_length_of_topic_subject)
				$lasttopicdissubject = mb_substr($lasttopicdissubject, 0, $max_length_of_topic_subject-2,"UTF-8") . "..";
			$lasttopic = highlight_topic(htmlspecialchars($lasttopicdissubject), $hlcolor);

			$lastpost = "<a href=\"".htmlspecialchars("?action=viewtopic&topicid=".$lasttopicid."&page=last#last")."\" title=\"".htmlspecialchars($lasttopicsubject)."\">".$lasttopic."</a><br />". $lastpostdate."&nbsp;|&nbsp;".get_username($lastposterid);

			$lastreadpost = get_last_read_post_id($lasttopicid);

			if ($lastreadpost >= $lastpostid)
				$img = get_topic_image("read");
			else
				$img = get_topic_image("unread");
		}
		else
		{
			$lastpost = "N/A";
			$img = get_topic_image("read");
		}
		$posttodaycount = $Cache->get_value('forum_'.$forumid.'_post_'.$today_date.'_count');
		if ($posttodaycount == ""){
			$res3 = sql_query("SELECT COUNT(posts.id) FROM posts LEFT JOIN topics ON posts.topicid = topics.id WHERE posts.added > ".sqlesc(date("Y-m-d"))." AND topics.forumid=".sqlesc($forumid)) or sqlerr(__FILE__, __LINE__);
			$row3 = mysql_fetch_row($res3);
			$posttodaycount = $row3[0];
			$Cache->cache_value('forum_'.$forumid.'_post_'.$today_date.'_count', $posttodaycount, 1800);
		}
		if ($posttodaycount > 0)
			$posttoday = "&nbsp;&nbsp;(".$lang_forums['text_today']."<b><font class=\"new\">".$posttodaycount."</font></b>)";
		else $posttoday = "";
		print("<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\" style='padding-right: 10px'>".$img."</td><td class=\"embedded\"><a href=\"".htmlspecialchars("?action=viewforum&forumid=".$forumid)."\"><font class=\"big\"><b>".$forumname."</b></font></a>" .$posttoday.
		"<br />".$forumdescription."</td></tr></table></td><td class=\"rowfollow\" align=\"center\" width=\"1%\">".$topiccount."</td><td class=\"rowfollow\" align=\"center\" width=\"1%\">".$postcount."</td>" .
		"<td class=\"rowfollow nowrap\" align=\"left\">".$lastpost."</td><td class=\"rowfollow\" align=\"left\">".$forummoderators."</td></tr>\n");
	}
	$count++;
}
// End Table Mod
print("</table>");
if ($showforumstats_main == "yes")
	forum_stats();
end_main_frame();
stdfoot();
?>
