<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
require_once(get_langfile_path("",true));
loggedinorreturn();
$action=$_GET["action"];
if (!$action)
{
	$action = (string) $_POST['action'];
	if (!$action)
		$action = 'view';
}
if ($action == 'delete')
{
	$id = intval($_GET["id"] ?? 0);
	int_check($id,true);
	$res = sql_query("SELECT userid FROM fun WHERE id=$id") or sqlerr(__FILE__,__LINE__);
	$arr = mysql_fetch_array($res);
	if (!$arr)
		stderr($lang_fun['std_error'], $lang_fun['std_invalid_id']);
	if (get_user_class() < $funmanage_class)
		permissiondenied();
	$sure = intval($_GET["sure"] ?? 0);
	$returnto = $_GET["returnto"] ? htmlspecialchars($_GET["returnto"]) : htmlspecialchars($_SERVER["HTTP_REFERER"]);
	if (!$sure)
		stderr($lang_fun['std_delete_fun'],$lang_fun['text_please_click'] ."<a class=altlink href=?action=delete&id=$id&returnto=$returnto&sure=1>".$lang_fun['text_here_if_sure'],false);
	sql_query("DELETE FROM fun WHERE id=".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
	$Cache->delete_value('current_fun_content');
	$Cache->delete_value('current_fun', true);
	$Cache->delete_value('current_fun_vote_count');
	$Cache->delete_value('current_fun_vote_funny_count');
	if ($returnto != "")
	header("Location: $returnto");
}
if ($action == 'new')
{
	$sql = "SELECT *, IF(ADDTIME(added, '1 0:0:0') < NOW(),true,false) AS neednew FROM fun WHERE status != 'banned' AND status != 'dull' ORDER BY added DESC LIMIT 1";
	$result = sql_query($sql) or sqlerr(__FILE__,__LINE__);
	$row = mysql_fetch_array($result);
	if ($row && !$row['neednew'])
		stderr($lang_fun['std_error'],$lang_fun['std_the_newest_fun_item'].htmlspecialchars($row['title']).$lang_fun['std_posted_on'].$row['added'].$lang_fun['std_need_to_wait']);
	else {
	stdhead($lang_fun['head_new_fun']);
	begin_main_frame();
	$title = $lang_fun['text_submit_new_fun'];
	print("<form id=compose method=post name=\"compose\" action=?action=add>\n");
	begin_compose($title, 'new');
	end_compose();
	end_main_frame();
	}
	stdfoot();
}
if ($action == 'add')
{
	$sql = "SELECT *, IF(ADDTIME(added, '1 0:0:0') < NOW(),true,false) AS neednew FROM fun WHERE status != 'banned' AND status != 'dull' ORDER BY added DESC LIMIT 1";
	$result = sql_query($sql) or sqlerr(__FILE__,__LINE__);
	$row = mysql_fetch_array($result);
	if ($row && !$row['neednew'])
		stderr($lang_fun['std_error'],$lang_fun['std_the_newest_fun_item'].htmlspecialchars($row['title']).$lang_fun['std_posted_on'].$row['added'].$lang_fun['std_need_to_wait']);
	else {
	$body = $_POST['body'];
	if (!$body)
	stderr($lang_fun['std_error'],$lang_fun['std_body_is_empty']);
	$title = htmlspecialchars($_POST['subject']);
	if (!$title)
	stderr($lang_fun['std_error'],$lang_fun['std_title_is_empty']);
	$sql = "INSERT INTO fun (userid, added, body, title, status) VALUES (".sqlesc($CURUSER['id']).",".sqlesc(date("Y-m-d H:i:s")).",".sqlesc($body).",".sqlesc($title).", 'normal')";
	sql_query($sql) or sqlerr(__FILE__, __LINE__);
	$Cache->delete_value('current_fun_content');
	$Cache->delete_value('current_fun', true);
	$Cache->delete_value('current_fun_vote_count');
	$Cache->delete_value('current_fun_vote_funny_count');
	if (mysql_affected_rows() == 1)
	$warning = $lang_fun['std_fun_added_successfully'];
	else
	stderr($lang_fun['std_error'],$lang_fun['std_error_happened']);
	header("Location: " . get_protocol_prefix() . "$BASEURL/index.php");
	}
}
if ($action == 'view')
{
?>
<html><head>
<title><?php echo $lang_fun['head_fun']; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="<?php echo get_font_css_uri()?>" type="text/css">
<link rel="stylesheet" href="<?php echo get_css_uri()."theme.css"?>" type="text/css">
<link rel="stylesheet" href="styles/curtain_imageresizer.css" type="text/css">
<script src="js/curtain_imageresizer.js" type="text/javascript"></script><style type="text/css">body {overflow-y:scroll; overflow-x: hidden}</style>
</head><body class='inframe'>
<?php
print(get_style_addicode());
if (!$row = $Cache->get_value('current_fun_content')){
	$result = sql_query("SELECT fun.*, IF(ADDTIME(added, '1 0:0:0') < NOW(),true,false) AS neednew FROM fun WHERE status != 'banned' AND status != 'dull' ORDER BY added DESC LIMIT 1") or sqlerr(__FILE__,__LINE__);
	$row = mysql_fetch_array($result);
	$Cache->cache_value('current_fun_content', $row, 1043);
}
if ($row){
	$title = $row['title'];
	$username = get_username($row["userid"],false,true,true,true,false,false,"",false);
	if ($CURUSER['timetype'] != 'timealive')
		$time = $lang_fun['text_on'].$row['added'];
	else $time = $lang_fun['text_blank'].gettime($row['added'],true,false);
	$Cache->new_page('current_fun', 874, true);
	if (!$Cache->get_page()){
		$Cache->add_row();
		$Cache->add_part();
		print("<table border=0 cellspacing=0 cellpadding=2 width='100%'><tr><td class=shoutrow align=center><font class=big>".$title."</font><font class=small>".$lang_fun['text_posted_by']);
		$Cache->end_part();
		$Cache->add_part();
		print("</font></td></tr><tr><td class=shoutrow>\n");
		print(format_comment($row['body'], true, true, true)."</td></tr></table>");
		$Cache->end_part();
		$Cache->end_row();
		$Cache->cache_page();
	}
	while($Cache->next_row()){
		echo $Cache->next_part();
		print($username.$time);
		echo $Cache->next_part();
	}
}
print("</body></html>");
}
if ($action == 'edit'){
	$id = intval($_GET["id"] ?? 0);
	int_check($id,true);
	$res = sql_query("SELECT * FROM fun WHERE id=$id") or sqlerr(__FILE__,__LINE__);
	$arr = mysql_fetch_array($res);
	if (!$arr)
		stderr($lang_fun['std_error'], $lang_fun['std_invalid_id']);
	if ($arr["userid"] != $CURUSER["id"] && get_user_class() < $funmanage_class)
		permissiondenied();
	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		$body = $_POST['body'];

		if ($body == "")
		stderr($lang_fun['std_error'],$lang_fun['std_body_is_empty']);

		$title = htmlspecialchars($_POST['subject']);

		if ($title == "")
		stderr($lang_fun['std_error'],$lang_fun['std_title_is_empty']);

		$body = sqlesc($body);
		$title = sqlesc($title);
		sql_query("UPDATE fun SET body=$body, title=$title WHERE id=".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
		$Cache->delete_value('current_fun_content');
		$Cache->delete_value('current_fun', true);
		header("Location: " . get_protocol_prefix() . "$BASEURL/index.php");
	}
	else {
	stdhead($lang_fun['head_edit_fun']);
	begin_main_frame();
	$title = $lang_fun['text_edit_fun'];
	print("<form id=compose method=post name=\"compose\" action=?action=edit&id=".$id.">\n");
	begin_compose($title, 'edit',$arr['body'], true, $arr['title']);
	end_compose();
	end_main_frame();
	}
	stdfoot();
}
if ($action == 'ban')
{
	if (get_user_class() < $funmanage_class)
		permissiondenied();
	$id = intval($_GET["id"] ?? 0);
	int_check($id,true);
	$res = sql_query("SELECT * FROM fun WHERE id=$id") or sqlerr(__FILE__,__LINE__);
	$arr = mysql_fetch_array($res);
	if (!$arr)
		stderr($lang_fun['std_error'], $lang_fun['std_invalid_id']);
	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		$banreason = htmlspecialchars($_POST['banreason'],ENT_QUOTES);
		$title = htmlspecialchars($arr['title']);
		if ($banreason == "")
		stderr($lang_fun['std_error'],$lang_fun['std_reason_is_empty']);
		sql_query("UPDATE fun SET status='banned' WHERE id=".sqlesc($id)) or sqlerr(__FILE__, __LINE__);

		$Cache->delete_value('current_fun_content');
		$Cache->delete_value('current_fun', true);
		$Cache->delete_value('current_fun_vote_count');
		$Cache->delete_value('current_fun_vote_funny_count');

		$subject = $lang_fun_target[get_user_lang($arr['userid'])]['msg_fun_item_banned'];
		$msg = $lang_fun_target[get_user_lang($arr['userid'])]['msg_your_fun_item'].$title.$lang_fun_target[get_user_lang($arr['userid'])]['msg_is_ban_by'].$CURUSER['username'].$lang_fun_target[get_user_lang($arr['userid'])]['msg_reason'].$banreason;
		sql_query("INSERT INTO messages (sender, subject, receiver, added, msg) VALUES(0, ".sqlesc($subject).", ".$arr['userid'].", '" . date("Y-m-d H:i:s") . "', " . sqlesc($msg) . ")") or sqlerr(__FILE__, __LINE__);
		$Cache->delete_value('user_'.$arr['userid'].'_unread_message_count');
		$Cache->delete_value('user_'.$arr['userid'].'_inbox_count');
		write_log("Fun item $id ($title) was banned by {$CURUSER['username']}. Reason: $banreason", 'normal');
		stderr($lang_fun['std_success'], $lang_fun['std_fun_item_banned']);
	}
	else {
		stderr($lang_fun['std_are_you_sure'], $lang_fun['std_only_against_rule']."<br /><form name=ban method=post action=fun.php?action=ban&id=".$id."><input type=hidden name=sure value=1>".$lang_fun['std_reason_required']."<input type=text style=\"width: 200px\" name=banreason><input type=submit value=".$lang_fun['submit_okay']."></form>", false);
	}
}
function funreward($funvote, $totalvote, $title, $posterid, $bonus)
{
	global $lang_fun_target, $lang_fun, $Cache;
	KPS("+",$bonus,$posterid);
	$subject = $lang_fun_target[get_user_lang($posterid)]['msg_fun_item_reward'];
	$msg = $funvote.$lang_fun_target[get_user_lang($posterid)]['msg_out_of'].$totalvote.$lang_fun_target[get_user_lang($posterid)]['msg_people_think'].$title.$lang_fun_target[get_user_lang($posterid)]['msg_is_fun'].$bonus.$lang_fun_target[get_user_lang($posterid)]['msg_bonus_as_reward'];
	$sql = "INSERT INTO messages (sender, subject, receiver, added, msg) VALUES(0, ".sqlesc($subject).",". $posterid. ",'" . date("Y-m-d H:i:s") . "', " . sqlesc($msg) . ")";
	sql_query($sql) or sqlerr(__FILE__, __LINE__);
	$Cache->delete_value('user_'.$posterid.'_unread_message_count');
	$Cache->delete_value('user_'.$posterid.'_inbox_count');
}

if ($action == 'vote')
{
	$id = intval($_GET["id"] ?? 0);
	int_check($id,true);
	$res = sql_query("SELECT * FROM fun WHERE id=$id") or sqlerr(__FILE__,__LINE__);
	$arr = mysql_fetch_array($res);
	if (!$arr)
		stderr($lang_fun['std_error'], $lang_fun['std_invalid_id']);
	else {
		$res = sql_query("SELECT * FROM funvotes WHERE funid=$id AND userid = {$CURUSER['id']}") or sqlerr(__FILE__,__LINE__);
		$checkvote = mysql_fetch_array($res);
		if ($checkvote)
			stderr($lang_fun['std_error'], $lang_fun['std_already_vote']);
		else {
			if ($_GET["yourvote"] == 'dull')
				$vote = 'dull';
			else $vote = 'fun';
			$sql = "INSERT INTO funvotes (funid, userid, added, vote) VALUES (".sqlesc($id).",".$CURUSER['id'].",".sqlesc(date("Y-m-d H:i:s")).",".sqlesc($vote).")";
			sql_query($sql) or sqlerr(__FILE__,__LINE__);
			KPS("+",$funboxvote_bonus,$CURUSER['id']); //voter gets 1.0 bonus per vote
			$totalvote = $Cache->get_value('current_fun_vote_count');
			if ($totalvote == ""){
				$totalvote = get_row_count("funvotes", "WHERE funid = ".sqlesc($row['id']));
			}
			else $totalvote++;
			$Cache->cache_value('current_fun_vote_count', $totalvote, 756);
			$funvote = $Cache->get_value('current_fun_vote_funny_count');
			if ($funvote == ""){
				$funvote = get_row_count("funvotes", "WHERE funid = ".sqlesc($row['id'])." AND vote='fun'");
			}
			elseif($vote == 'fun')
				$funvote++;
			$Cache->cache_value('current_fun_vote_funny_count', $funvote, 756);
			if ($totalvote) $ratio = $funvote / $totalvote; else $ratio = 1;
			if ($totalvote >= 20){
				if ($ratio > 0.75){
					sql_query("UPDATE fun SET status = 'veryfunny' WHERE id = ".sqlesc($id));
					if ($totalvote == 25) //Give fun item poster some bonus and write a message to him
						funreward($funvote, $totalvote, $arr['title'], $arr['userid'], $funboxreward_bonus * 2);
					if ($totalvote == 50)
						funreward($funvote, $totalvote, $arr['title'], $arr['userid'], $funboxreward_bonus * 2);
					if ($totalvote == 100)
						funreward($funvote, $totalvote, $arr['title'], $arr['userid'], $funboxreward_bonus * 2);
					if ($totalvote == 200)
						funreward($funvote, $totalvote, $arr['title'], $arr['userid'], $funboxreward_bonus * 2);
					}
				elseif ($ratio > 0.5){
					sql_query("UPDATE fun SET status = 'funny' WHERE id = ".sqlesc($id));
					if ($totalvote == 25) //Give fun item poster some bonus and write a message to him
						funreward($funvote, $totalvote, $arr['id'], $arr['userid'], $funboxreward_bonus);
					if ($totalvote == 50)
						funreward($funvote, $totalvote, $arr['id'], $arr['userid'], $funboxreward_bonus);
					if ($totalvote == 100)
						funreward($funvote, $totalvote, $arr['id'], $arr['userid'], $funboxreward_bonus);
					if ($totalvote == 200)
						funreward($funvote, $totalvote, $arr['id'], $arr['userid'], $funboxreward_bonus);
					}
				elseif ($ratio > 0.25){
					sql_query("UPDATE fun SET status = 'notfunny' WHERE id = ".sqlesc($id));
				}
				else{
					sql_query("UPDATE fun SET status = 'dull' WHERE id = ".sqlesc($id));
				 	//write a message to fun item poster
					$subject = $lang_fun_target[get_user_lang($arr['userid'])]['msg_fun_item_dull'];
					$msg = ($totalvote - $funvote).$lang_fun_target[get_user_lang($arr['userid'])]['msg_out_of'].$totalvote.$lang_fun_target[get_user_lang($arr['userid'])]['msg_people_think'].$arr['title'].$lang_fun_target[get_user_lang($arr['userid'])]['msg_is_dull'];
					$sql = "INSERT INTO messages (sender, subject, receiver, added, msg) VALUES(0, ".sqlesc($subject).",". $arr['userid'].", '" . date("Y-m-d H:i:s") . "', " . sqlesc($msg) . ")";
					sql_query($sql) or sqlerr(__FILE__, __LINE__);
					$Cache->delete_value('user_'.$arr['userid'].'_unread_message_count');
					$Cache->delete_value('user_'.$arr['userid'].'_inbox_count');
				}
			}
		}
	}
}
?>
