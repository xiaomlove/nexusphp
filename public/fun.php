<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
//require_once(get_langfile_path("",true));
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
	$arr = \Nexus\Database\NexusDB::table('fun')
		->where('id', (int) $id)
		->select(['userid'])
		->first();
	$arr = $arr ? (array) $arr : null;
	if (!$arr)
		stderr($lang_fun['std_error'], $lang_fun['std_invalid_id']);
	user_can('funmanage', true);
	$sure = intval($_GET["sure"] ?? 0);
	$returnto = $_GET["returnto"] ? htmlspecialchars($_GET["returnto"]) : htmlspecialchars($_SERVER["HTTP_REFERER"]);
	if (!$sure)
		stderr($lang_fun['std_delete_fun'],$lang_fun['text_please_click'] ."<a class=altlink href=?action=delete&id=$id&returnto=$returnto&sure=1>".$lang_fun['text_here_if_sure'],false);
	\Nexus\Database\NexusDB::table('fun')->where('id', (int) $id)->delete();
	$Cache->delete_value('current_fun_content');
	$Cache->delete_value('current_fun', true);
	$Cache->delete_value('current_fun_vote_count');
	$Cache->delete_value('current_fun_vote_funny_count');
	if ($returnto != "")
	header("Location: $returnto");
}
if ($action == 'new')
{
	$row = \Nexus\Database\NexusDB::table('fun')
		->whereNotIn('status', ['banned', 'dull'])
		->orderByDesc('added')
		->limit(1)
		->select(['fun.*', \Nexus\Database\NexusDB::raw("IF(ADDTIME(added, '1 0:0:0') < NOW(),true,false) AS neednew")])
		->first();
	$row = $row ? (array) $row : null;
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
	$row = \Nexus\Database\NexusDB::table('fun')
		->whereNotIn('status', ['banned', 'dull'])
		->orderByDesc('added')
		->limit(1)
		->select(['fun.*', \Nexus\Database\NexusDB::raw("IF(ADDTIME(added, '1 0:0:0') < NOW(),true,false) AS neednew")])
		->first();
	$row = $row ? (array) $row : null;
	if ($row && !$row['neednew'])
		stderr($lang_fun['std_error'],$lang_fun['std_the_newest_fun_item'].htmlspecialchars($row['title']).$lang_fun['std_posted_on'].$row['added'].$lang_fun['std_need_to_wait']);
	else {
	$body = $_POST['body'];
	if (!$body)
	stderr($lang_fun['std_error'],$lang_fun['std_body_is_empty']);
	$title = htmlspecialchars($_POST['subject']);
	if (!$title)
	stderr($lang_fun['std_error'],$lang_fun['std_title_is_empty']);
	$insertId = (int) \Nexus\Database\NexusDB::insert('fun', [
		'userid' => (int) $CURUSER['id'],
		'added' => date("Y-m-d H:i:s"),
		'body' => (string) $body,
		'title' => (string) $title,
		'status' => 'normal',
	]);
	$Cache->delete_value('current_fun_content');
	$Cache->delete_value('current_fun', true);
	$Cache->delete_value('current_fun_vote_count');
	$Cache->delete_value('current_fun_vote_funny_count');
	if ($insertId > 0)
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
	$row = \Nexus\Database\NexusDB::table('fun')
		->whereNotIn('status', ['banned', 'dull'])
		->orderByDesc('added')
		->limit(1)
		->select(['fun.*', \Nexus\Database\NexusDB::raw("IF(ADDTIME(added, '1 0:0:0') < NOW(),true,false) AS neednew")])
		->first();
	$row = $row ? (array) $row : null;
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
	$arr = \Nexus\Database\NexusDB::table('fun')
		->where('id', (int) $id)
		->first();
	$arr = $arr ? (array) $arr : null;
	if (!$arr)
		stderr($lang_fun['std_error'], $lang_fun['std_invalid_id']);
	if ($arr["userid"] != $CURUSER["id"] && !user_can('funmanage'))
		permissiondenied();
	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		$body = $_POST['body'];

		if ($body == "")
		stderr($lang_fun['std_error'],$lang_fun['std_body_is_empty']);

		$title = htmlspecialchars($_POST['subject']);

		if ($title == "")
		stderr($lang_fun['std_error'],$lang_fun['std_title_is_empty']);

		\Nexus\Database\NexusDB::table('fun')
			->where('id', (int) $id)
			->update([
				'body' => (string) $body,
				'title' => (string) $title,
			]);
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
	user_can('funmanage', true);
	$id = intval($_GET["id"] ?? 0);
	int_check($id,true);
	$arr = \Nexus\Database\NexusDB::table('fun')
		->where('id', (int) $id)
		->first();
	$arr = $arr ? (array) $arr : null;
	if (!$arr)
		stderr($lang_fun['std_error'], $lang_fun['std_invalid_id']);
	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		$banreason = htmlspecialchars($_POST['banreason'],ENT_QUOTES);
		$title = htmlspecialchars($arr['title']);
		if ($banreason == "")
		stderr($lang_fun['std_error'],$lang_fun['std_reason_is_empty']);
		\Nexus\Database\NexusDB::table('fun')
			->where('id', (int) $id)
			->update(['status' => 'banned']);

		$Cache->delete_value('current_fun_content');
		$Cache->delete_value('current_fun', true);
		$Cache->delete_value('current_fun_vote_count');
		$Cache->delete_value('current_fun_vote_funny_count');

		$locale = get_user_lang($arr['userid']);
        $subject = nexus_trans("fun.msg_fun_item_banned", [], $locale);
        $msg = nexus_trans("fun.msg_your_fun_item", [], $locale).$title.nexus_trans("fun.msg_is_ban_by", [], $locale).$CURUSER['username'].nexus_trans("fun.msg_reason", [], $locale).$banreason;

		\App\Models\Message::add([
			'sender' => 0,
			'receiver' => $arr['userid'],
			'subject' => $subject,
			'added' => now(),
			'msg' => $msg,
		]);

		write_log("Fun item $id ($title) was banned by {$CURUSER['username']}. Reason: $banreason", 'normal');
		stderr($lang_fun['std_success'], $lang_fun['std_fun_item_banned']);
	}
	else {
		stderr($lang_fun['std_are_you_sure'], $lang_fun['std_only_against_rule']."<br /><form name=ban method=post action=fun.php?action=ban&id=".$id."><input type=hidden name=sure value=1>".$lang_fun['std_reason_required']."<input type=text style=\"width: 200px\" name=banreason><input type=submit value=".$lang_fun['submit_okay']."></form>", false);
	}
}
function funreward($funvote, $totalvote, $title, $posterid, $bonus)
{
	global $lang_fun, $Cache;
	KPS("+",$bonus,$posterid);
	$locale = get_user_lang($posterid);
    $subject = nexus_trans("fun.msg_fun_item_reward", [], $locale);
    $msg = $funvote.nexus_trans("fun.msg_out_of", [], $locale).$totalvote.nexus_trans("fun.msg_people_think", [], $locale).$title.nexus_trans("fun.msg_is_fun", [], $locale).$bonus.nexus_trans("fun.msg_bonus_as_reward", [], $locale);

	\App\Models\Message::add([
			'sender' => 0,
			'receiver' => $posterid,
			'subject' => $subject,
			'added' => now(),
			'msg' => $msg,
	]);

	$Cache->delete_value('user_'.$posterid.'_unread_message_count');
	$Cache->delete_value('user_'.$posterid.'_inbox_count');
}

if ($action == 'vote')
{
	$id = intval($_GET["id"] ?? 0);
	int_check($id,true);
	$arr = \Nexus\Database\NexusDB::table('fun')
		->where('id', (int) $id)
		->first();
	$arr = $arr ? (array) $arr : null;
	if (!$arr)
		stderr($lang_fun['std_error'], $lang_fun['std_invalid_id']);
	else {
		$checkvote = \Nexus\Database\NexusDB::table('funvotes')
			->where('funid', (int) $id)
			->where('userid', (int) $CURUSER['id'])
			->first();
		if ($checkvote)
			stderr($lang_fun['std_error'], $lang_fun['std_already_vote']);
		else {
			if ($_GET["yourvote"] == 'dull')
				$vote = 'dull';
			else $vote = 'fun';
			\Nexus\Database\NexusDB::insert('funvotes', [
				'funid' => (int) $id,
				'userid' => (int) $CURUSER['id'],
				'added' => date("Y-m-d H:i:s"),
				'vote' => (string) $vote,
			]);
			KPS("+",$funboxvote_bonus,$CURUSER['id']); //voter gets 1.0 bonus per vote
			$totalvote = $Cache->get_value('current_fun_vote_count');
			if ($totalvote == ""){
				$totalvote = \Nexus\Database\NexusDB::table('funvotes')
					->where('funid', (int) ($row['id'] ?? 0))
					->count();
			}
			else $totalvote++;
			$Cache->cache_value('current_fun_vote_count', $totalvote, 756);
			$funvote = $Cache->get_value('current_fun_vote_funny_count');
			if ($funvote == ""){
				$funvote = \Nexus\Database\NexusDB::table('funvotes')
					->where('funid', (int) ($row['id'] ?? 0))
					->where('vote', 'fun')
					->count();
			}
			elseif($vote == 'fun')
				$funvote++;
			$Cache->cache_value('current_fun_vote_funny_count', $funvote, 756);
			if ($totalvote) $ratio = $funvote / $totalvote; else $ratio = 1;
			if ($totalvote >= 20){
				if ($ratio > 0.75){
					\Nexus\Database\NexusDB::table('fun')->where('id', (int) $id)->update(['status' => 'veryfunny']);
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
					\Nexus\Database\NexusDB::table('fun')->where('id', (int) $id)->update(['status' => 'funny']);
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
					\Nexus\Database\NexusDB::table('fun')->where('id', (int) $id)->update(['status' => 'notfunny']);
				}
				else{
					\Nexus\Database\NexusDB::table('fun')->where('id', (int) $id)->update(['status' => 'dull']);
				 	//write a message to fun item poster
                    $locale = get_user_locale($arr['userid']);
                    $subject = nexus_trans("fun.msg_fun_item_dull", [], $locale);
                    $msg = ($totalvote - $funvote).nexus_trans("fun.msg_out_of", [], $locale).$totalvote.nexus_trans("fun.msg_people_think", [], $locale).$arr['title'].nexus_trans("fun.msg_is_dull", [], $locale);
                    \App\Models\Message::add([
                        'sender' => 0,
                        'receiver' => $arr['userid'],
                        'subject' => $subject,
                        'added' => now(),
                        'msg' => $msg,
                    ]);
				}
			}
		}
	}
}
?>
