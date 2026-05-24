<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
user_can('newsmanage', true);

$action = htmlspecialchars($_GET["action"] ?? '');

//  Delete News Item    //////////////////////////////////////////////////////

if ($action == 'delete')
{
	$newsid = intval($_GET["newsid"] ?? 0);
	int_check($newsid,true);

	$returnto = !empty($_GET["returnto"]) ? htmlspecialchars($_GET["returnto"]) : htmlspecialchars($_SERVER["HTTP_REFERER"]);

	$sure = intval($_GET["sure"] ?? 0);
	if (!$sure)
	stderr($lang_news['std_delete_news_item'], $lang_news['std_are_you_sure'] . "<a class=altlink href=?action=delete&newsid=$newsid&returnto=$returnto&sure=1>".$lang_news['std_here']."</a>".$lang_news['std_if_sure'],false);

	\Nexus\Database\NexusDB::table('news')
		->where('id', (int) $newsid)
		->delete();
	$Cache->delete_value('recent_news','true');
	if ($returnto != "")
	header("Location: $returnto");
	else
	header("Location: " . get_protocol_prefix() . "$BASEURL/index.php");
}

//  Add News Item    /////////////////////////////////////////////////////////

if ($action == 'add')
{
	$body = htmlspecialchars($_POST['body'],ENT_QUOTES);
	if (!$body)
	stderr($lang_news['std_error'], $lang_news['std_news_body_empty']);

	$title = htmlspecialchars($_POST['subject']);
	if (!$title)
	stderr($lang_news['std_error'], $lang_news['std_news_title_empty']);

	$addedRaw = $_POST["added"] ?? 0;
	$addedInt = intval($addedRaw);
	$addedDate = $addedInt ? date("Y-m-d H:i:s", $addedInt) : date("Y-m-d H:i:s");
	$notify = $_POST['notify'] ?? '';
	if ($notify != 'yes')
		$notify = 'no';
	$newsId = (int) \Nexus\Database\NexusDB::insert('news', [
		'userid' => (int) $CURUSER['id'],
		'added' => $addedDate,
		'body' => (string) $body,
		'title' => (string) $title,
		'notify' => (string) $notify,
	]);
	$Cache->delete_value('recent_news',true);
	if (!$newsId) {
        stderr($lang_news['std_error'], $lang_news['std_something_weird_happened']);
    }
	fire_event("news_created", \App\Models\News::query()->find($newsId));
	header("Location: " . get_protocol_prefix() . "$BASEURL/index.php");
}

//  Edit News Item    ////////////////////////////////////////////////////////

if ($action == 'edit')
{

	$newsid = intval($_GET["newsid"] ?? 0);
	int_check($newsid,true);

	$rows = \Nexus\Database\NexusDB::select("SELECT * FROM news WHERE id=" . (int) $newsid);

	if (count($rows) != 1)
	stderr($lang_news['std_error'], $lang_news['std_invalid_news_id'].$newsid);

	$arr = $rows[0];

	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		$body = htmlspecialchars($_POST['body'],ENT_QUOTES);

		if ($body == "")
		stderr($lang_news['std_error'], $lang_news['std_news_body_empty']);

		$title = htmlspecialchars($_POST['subject']);

		if ($title == "")
		stderr($lang_news['std_error'], $lang_news['std_news_title_empty']);

		$notify = $_POST['notify'] ?? '';
		if ($notify != 'yes')
			$notify = 'no';
		\Nexus\Database\NexusDB::table('news')
			->where('id', (int) $newsid)
			->update([
				'body' => (string) $body,
				'title' => (string) $title,
				'notify' => (string) $notify,
			]);
		$Cache->delete_value('recent_news',true);
		header("Location: " . get_protocol_prefix() . "$BASEURL/index.php");
	}
	else
	{
		stdhead($lang_news['head_edit_site_news']);
		begin_main_frame();
		$body = $arr["body"];
		$subject = htmlspecialchars($arr['title']);
		$title = $lang_news['text_edit_site_news'];
		print("<form id=\"compose\" name=\"compose\" method=\"post\" action=\"".htmlspecialchars("?action=edit&newsid=".$newsid)."\">");
		print("<input type=\"hidden\" name=\"returnto\" value=\"".($returnto ?? '')."\" />");
		begin_compose($title, "edit", $body, true, $subject);
		print("<tr><td class=\"toolbox\" align=\"center\" colspan=\"2\"><input type=\"checkbox\" name=\"notify\" value=\"yes\" ".($arr['notify'] == 'yes' ? " checked=\"checked\"" : "")." />".$lang_news['text_notify_users_of_this']."</td></tr>\n");
		end_compose();
		end_main_frame();
		stdfoot();
		die;
	}

}

//  Other Actions and followup    ////////////////////////////////////////////

stdhead($lang_news['head_site_news']);
begin_main_frame();
$title = $lang_news['text_submit_news_item'];
print("<form id=\"compose\" method=\"post\" name=\"compose\" action=\"?action=add\">\n");
begin_compose($title, 'new');
print("<tr><td class=\"toolbox\" align=\"center\" colspan=\"2\"><input type=\"checkbox\" name=\"notify\" value=\"yes\" />".$lang_news['text_notify_users_of_this']."</td></tr>\n");
end_compose();
print("</form>");
end_main_frame();
stdfoot();
die;

?>
