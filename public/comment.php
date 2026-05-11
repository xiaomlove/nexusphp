<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
//require(get_langfile_path("",true));

$action = htmlspecialchars($_GET["action"]);
$sub = htmlspecialchars($_GET["sub"] ?? '');
$type = htmlspecialchars($_GET["type"]);

loggedinorreturn();
parked();

function check_comment_type($type)
{
	global $lang_comment;
	if($type != "torrent" && $type != "request" && $type != "offer")
	stderr($lang_comment['std_error'],$lang_comment['std_error']);
}

check_comment_type($type);

if ($action == "add")
{

	if ($_SERVER["REQUEST_METHOD"] == "POST")
	{
		// Anti Flood Code
		// This code ensures that a member can only send one comment per minute.
		if (!user_can('commanage')) {
			if (strtotime($CURUSER['last_comment']) > (TIMENOW - 10))
			{
				$secs = 10 - (TIMENOW - strtotime($CURUSER['last_comment']));
				stderr($lang_comment['std_error'],$lang_comment['std_comment_flooding_denied']."$secs".$lang_comment['std_before_posting_another']);
			}
		}

		$parent_id = intval($_POST["pid"] ?? 0);
		int_check($parent_id,true);

		if($type == "torrent")
			$rows = \Nexus\Database\NexusDB::select("SELECT name, owner FROM torrents WHERE id = $parent_id");
		else if($type == "offer")
			$rows = \Nexus\Database\NexusDB::select("SELECT name, userid as owner FROM offers WHERE id = $parent_id");
		else if($type == "request")
			$rows = \Nexus\Database\NexusDB::select("SELECT requests.request as name, userid as owner FROM requests WHERE id = $parent_id");

		$arr = $rows[0] ?? null;
		if (!$arr)
			stderr($lang_comment['std_error'], $lang_comment['std_no_torrent_id']);

		$text = trim($_POST["body"]);
		if (!$text)
			stderr($lang_comment['std_error'], $lang_comment['std_comment_body_empty']);

		$insertData = [
			'user' => (int) $CURUSER["id"],
			'added' => date("Y-m-d H:i:s"),
			'text' => (string) $text,
			'ori_text' => (string) $text,
		];
		if($type == "torrent"){
			$insertData['torrent'] = $parent_id;
			$newid = (int) \Nexus\Database\NexusDB::insert('comments', $insertData);
			$Cache->delete_value('torrent_'.$parent_id.'_last_comment_content');
		}
		elseif($type == "offer"){
			$insertData['offer'] = $parent_id;
			$newid = (int) \Nexus\Database\NexusDB::insert('comments', $insertData);
			$Cache->delete_value('offer_'.$parent_id.'_last_comment_content');
		}
		elseif($type == "request") {
			$insertData['request'] = $parent_id;
			$newid = (int) \Nexus\Database\NexusDB::insert('comments', $insertData);
		}

		if($type == "torrent")
			\Nexus\Database\NexusDB::statement("UPDATE torrents SET comments = comments + 1 WHERE id = $parent_id");
		else if($type == "offer")
			\Nexus\Database\NexusDB::statement("UPDATE offers SET comments = comments + 1 WHERE id = $parent_id");
		else if($type == "request")
			\Nexus\Database\NexusDB::statement("UPDATE requests SET comments = comments + 1 WHERE id = $parent_id");

		$arg = \Nexus\Database\NexusDB::table('users')
			->where('id', (int) $arr['owner'])
			->select(['commentpm'])
			->first();
		$arg = $arg ? (array) $arg : [];

		if($arg["commentpm"] == 'yes' && $CURUSER['id'] != $arr["owner"])
		{
            $locale = get_user_locale($arr['owner']);
			$subject = nexus_trans("comment.msg_new_comment", [], $locale);
			if($type == "torrent")
			$notifs = nexus_trans("comment.msg_torrent_receive_comment", [], $locale) . " [url=" . get_protocol_prefix() . "$BASEURL/details.php?id=$parent_id] " . $arr['name'] . "[/url].";
			if($type == "offer")
			$notifs = nexus_trans("comment.msg_torrent_receive_comment", [], $locale) . " [url=" . get_protocol_prefix() . "$BASEURL/offers.php?id=$parent_id&off_details=1] " . $arr['name'] . "[/url].";
			if($type == "request")
			$notifs = nexus_trans("comment.msg_torrent_receive_comment", [], $locale). " [url=" . get_protocol_prefix() . "$BASEURL/viewrequests.php?id=$parent_id&req_details=1] " . $arr['name'] . "[/url].";

			\App\Models\Message::add([
				'sender' => 0,
				'receiver' => $arr['owner'],
				'subject' => $subject,
				'added' => now(),
				'msg' => $notifs,
			]);
		}

		KPS("+",$addcomment_bonus,$CURUSER["id"]);

		// Update Last comment sent...
		\Nexus\Database\NexusDB::table('users')
			->where('id', (int) $CURUSER['id'])
			->update(['last_comment' => \Nexus\Database\NexusDB::raw('NOW()')]);

		if($type == "torrent")
			header("Location: details.php?id=$parent_id#$newid");
		else if($type == "offer")
			header("Location: offers.php?id=$parent_id&off_details=1#$newid");
		else if($type == "request")
			header("Location: viewrequests.php?id=$parent_id&req_details=1#$newid");
		die;
	}

	$parent_id = intval($_GET["pid"] ?? 0);
	int_check($parent_id,true);

	if($sub == "quote")
	{
		$commentid = intval($_GET["cid"] ?? 0);
		int_check($commentid,true);

		$rows2 = \Nexus\Database\NexusDB::select("SELECT comments.text, users.username FROM comments LEFT JOIN users ON comments.user = users.id WHERE comments.id=$commentid");

		if (count($rows2) != 1)
			stderr($lang_comment['std_error'], $lang_comment['std_no_comment_id']);

		$arr2 = $rows2[0];
	}

	if($type == "torrent"){
		$rows = \Nexus\Database\NexusDB::select("SELECT name, owner FROM torrents WHERE id = $parent_id");
		$url="details.php?id=$parent_id";
	}
	else if($type == "offer"){
		$rows = \Nexus\Database\NexusDB::select("SELECT name, userid as owner FROM offers WHERE id = $parent_id");
		$url="offers.php?id=$parent_id&off_details=1";
	}
	else if($type == "request"){
		$rows = \Nexus\Database\NexusDB::select("SELECT requests.request as name, userid as owner FROM requests WHERE id = $parent_id");
		$url="viewrequests.php?id=$parent_id&req_details=1";
	}
	$arr = $rows[0] ?? null;
	if (!$arr)
		stderr($lang_comment['std_error'], $lang_comment['std_no_torrent_id']);

	stdhead($lang_comment['head_add_comment_to']. $arr["name"]);
	begin_main_frame();
	$title = $lang_comment['text_add_comment_to']."<a href=$url>". htmlspecialchars($arr["name"]) . "</a>";
	print("<form id=compose method=post name=\"compose\" action=\"comment.php?action=add&type=$type\">\n");
	print("<input type=\"hidden\" name=\"pid\" value=\"$parent_id\"/>\n");
	begin_compose($title, ($sub == "quote" ? "quote" : "reply"), ($sub == "quote" ? htmlspecialchars("[quote=".htmlspecialchars($arr2["username"])."]".unesc($arr2["text"])."[/quote]") : ""), false);
	end_compose();
	print("</form>");
	end_main_frame();
	stdfoot();
	die;
}
elseif ($action == "edit")
{
		$commentid = intval($_GET["cid"] ?? 0);
		int_check($commentid,true);

		if($type == "torrent")
			$rows = \Nexus\Database\NexusDB::select("SELECT c.*, t.name, t.id AS parent_id FROM comments AS c JOIN torrents AS t ON c.torrent = t.id WHERE c.id=$commentid");
		else if($type == "offer")
			$rows = \Nexus\Database\NexusDB::select("SELECT c.*, o.name, o.id AS parent_id FROM comments AS c JOIN offers AS o ON c.offer = o.id WHERE c.id=$commentid");
		else if($type == "request")
			$rows = \Nexus\Database\NexusDB::select("SELECT c.*, r.request as name, r.id AS parent_id FROM comments AS c JOIN requests AS r ON c.request = r.id WHERE c.id=$commentid");

		$arr = $rows[0] ?? null;
		if (!$arr)
		stderr($lang_comment['std_error'], $lang_comment['std_invalid_id']);

		if ($arr["user"] != $CURUSER["id"] && !user_can('commanage'))
		stderr($lang_comment['std_error'], $lang_comment['std_permission_denied']);

		if ($_SERVER["REQUEST_METHOD"] == "POST")
		{
			$text = $_POST["body"];
			$returnto =  htmlspecialchars($_POST["returnto"]) ? $_POST["returnto"] : htmlspecialchars($_SERVER["HTTP_REFERER"]);

			if ($text == "")
				stderr($lang_comment['std_error'], $lang_comment['std_comment_body_empty']);

			$previousBody = (string) ($arr['text'] ?? '');
			if ($previousBody !== (string) $text) {
				try {
					\App\Models\CommentEdit::query()->insert([
						'commentid' => (int) $commentid,
						'editor_userid' => (int) $CURUSER['id'],
						'body_before' => $previousBody,
						'edited_at' => date("Y-m-d H:i:s"),
					]);
				} catch (\Throwable $e) {
					do_log('[comment] edit snapshot failed: '.$e->getMessage(), 'error');
				}
			}
			\Nexus\Database\NexusDB::table('comments')
				->where('id', (int) $commentid)
				->update([
					'text' => (string) $text,
					'editdate' => date("Y-m-d H:i:s"),
					'editedby' => (int) $CURUSER['id'],
				]);
			if($type == "torrent")
				$Cache->delete_value('torrent_'.$arr['parent_id'].'_last_comment_content');
			elseif ($type == "offer")
				$Cache->delete_value('offer_'.$arr['parent_id'].'_last_comment_content');
			header("Location: $returnto");

			die;
		}
		$parent_id = $arr["parent_id"];
		if($type == "torrent")
			$url="details.php?id=$parent_id";
		else if($type == "offer")
			$url="offers.php?id=$parent_id&off_details=1";
		else if($type == "request")
			$url="viewrequests.php?id=$parent_id&req_details=1";
		stdhead($lang_comment['head_edit_comment_to']."\"". $arr["name"] . "\"");
		begin_main_frame();
		$title = $lang_comment['head_edit_comment_to']."<a href=$url>". htmlspecialchars($arr["name"]) . "</a>";
		print("<form id=compose method=post name=\"compose\" action=\"comment.php?action=edit&cid=$commentid&type=$type\">\n");
		print("<input type=\"hidden\" name=\"returnto\" value=\"" . htmlspecialchars($_SERVER["HTTP_REFERER"]) . "\" />\n");
		begin_compose($title, "edit", htmlspecialchars(unesc($arr["text"])), false);
		end_compose();
		print("</form>");
		$historyCount = (int) \App\Models\CommentEdit::query()->where('commentid', (int) $commentid)->count();
		if ($historyCount > 0) {
			print('<p><font size="small">(<a href="comment.php?action=history&cid='.(int) $commentid.'&type='.htmlspecialchars($type).'">View edit history ('.$historyCount.')</a>)</font></p>');
		}
		end_main_frame();
		stdfoot();
		die;
}
elseif ($action == "delete")
{
		if (!user_can('commanage'))
		stderr($lang_comment['std_error'], $lang_comment['std_permission_denied']);

		$commentid = intval($_GET["cid"] ?? 0);
		$sure = $_GET["sure"];
		int_check($commentid,true);

		if (!$sure)
		{
			$referer = $_SERVER["HTTP_REFERER"];
			stderr($lang_comment['std_delete_comment'], $lang_comment['std_delete_comment_note'] ."<a href=comment.php?action=delete&cid=$commentid&sure=1&type=$type" .($referer ? "&returnto=" . rawurlencode($referer) : "") . $lang_comment['std_here_if_sure'],false);
		}
		else
		int_check($sure,true);


		if($type == "torrent")
		$rows = \Nexus\Database\NexusDB::select("SELECT torrent as pid,user FROM comments WHERE id=$commentid");
		else if($type == "offer")
		$rows = \Nexus\Database\NexusDB::select("SELECT offer as pid,user FROM comments WHERE id=$commentid");
		else if($type == "request")
		$rows = \Nexus\Database\NexusDB::select("SELECT request as pid,user FROM comments WHERE id=$commentid");

		$arr = $rows[0] ?? null;
		if ($arr)
		{
			$parent_id = $arr["pid"];
			$userpostid = $arr["user"];
		}
		else
		stderr($lang_comment['std_error'], $lang_comment['std_invalid_id']);

		$deleted = \Nexus\Database\NexusDB::table('comments')
			->where('id', (int) $commentid)
			->delete();
		if ($type == "torrent")
			$Cache->delete_value('torrent_'.$arr['pid'].'_last_comment_content');
		elseif ($type == "offer")
			$Cache->delete_value('offer_'.$arr['pid'].'_last_comment_content');
		if ($parent_id && $deleted > 0)
		{
			if($type == "torrent")
			\Nexus\Database\NexusDB::statement("UPDATE torrents SET comments = comments - 1 WHERE id = " . (int) $parent_id);
			else if($type == "offer")
			\Nexus\Database\NexusDB::statement("UPDATE offers SET comments = comments - 1 WHERE id = " . (int) $parent_id);
			else if($type == "request")
			\Nexus\Database\NexusDB::statement("UPDATE requests SET comments = comments - 1 WHERE id = " . (int) $parent_id);
		}

		KPS("-",$addcomment_bonus,$userpostid);

		$returnto = $_GET["returnto"] ? $_GET["returnto"] : htmlspecialchars($_SERVER["HTTP_REFERER"]);

		header("Location: $returnto");

		die;
}
elseif ($action == "vieworiginal")
{
	if (!user_can('commanage'))
	stderr($lang_comment['std_error'], $lang_comment['std_permission_denied']);

		$commentid = intval($_GET["cid"] ?? 0);
		int_check($commentid,true);

		if($type == "torrent")
		$rows = \Nexus\Database\NexusDB::select("SELECT c.*, t.name FROM comments AS c JOIN torrents AS t ON c.torrent = t.id WHERE c.id=$commentid");
		else if($type == "offer")
		$rows = \Nexus\Database\NexusDB::select("SELECT c.*, o.name FROM comments AS c JOIN offers AS o ON c.offer = o.id WHERE c.id=$commentid");
		else if($type == "request")
		$rows = \Nexus\Database\NexusDB::select("SELECT c.*, r.request as name FROM comments AS c JOIN requests AS r ON c.request = r.id WHERE c.id=$commentid");

		$arr = $rows[0] ?? null;
		if (!$arr)
		stderr($lang_comment['std_error'], $lang_comment['std_invalid_id']);

		stdhead($lang_comment['head_original_comment']);
		print("<h1>".$lang_comment['text_original_content_of_comment']."#$commentid</h1>");
		print("<table width=\"737\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">");
		print("<tr><td class=\"text\">\n");
		echo format_comment($arr["ori_text"]);
		print("</td></tr></table>\n");

		$returnto =  htmlspecialchars($_SERVER["HTTP_REFERER"]);

		if ($returnto)
		print("<p><font size=\"small\">(<a href=\"".$returnto."\">".$lang_comment['text_back']."</a>)</font></p>\n");

		stdfoot();

		die;
}
elseif ($action == "history")
{
	$commentid = intval($_GET["cid"] ?? 0);
	int_check($commentid, true);

	if ($type == "torrent") {
		$rows = \Nexus\Database\NexusDB::select("SELECT c.*, t.name FROM comments AS c JOIN torrents AS t ON c.torrent = t.id WHERE c.id=$commentid");
	} elseif ($type == "offer") {
		$rows = \Nexus\Database\NexusDB::select("SELECT c.*, o.name FROM comments AS c JOIN offers AS o ON c.offer = o.id WHERE c.id=$commentid");
	} elseif ($type == "request") {
		$rows = \Nexus\Database\NexusDB::select("SELECT c.*, r.request as name FROM comments AS c JOIN requests AS r ON c.request = r.id WHERE c.id=$commentid");
	} else {
		$rows = [];
	}

	$arr = $rows[0] ?? null;
	if (!$arr) {
		stderr($lang_comment['std_error'], $lang_comment['std_invalid_id']);
	}
	if ($arr['user'] != $CURUSER['id'] && !user_can('commanage')) {
		stderr($lang_comment['std_error'], $lang_comment['std_permission_denied']);
	}

	$history = \App\Models\CommentEdit::query()
		->with('editor:id,username')
		->where('commentid', (int) $commentid)
		->orderByDesc('edited_at')
		->orderByDesc('id')
		->get();

	stdhead("Comment edit history");
	print('<h1>Edit history of comment #'.(int) $commentid.'</h1>');
	if ($history->isEmpty()) {
		print('<p>No edits recorded for this comment yet.</p>');
	} else {
		print('<table border="1" cellspacing="0" cellpadding="5" width="737">');
		foreach ($history as $entry) {
			$editorName = htmlspecialchars((string) ($entry->editor?->username ?? 'unknown'));
			$at = $entry->edited_at ? $entry->edited_at->format('Y-m-d H:i:s').' ('.$entry->edited_at->diffForHumans().')' : '-';
			print('<tr><td class="colhead"><b>'.$editorName.'</b> &middot; '.$at.'</td></tr>');
			print('<tr><td class="text">'.format_comment((string) $entry->body_before).'</td></tr>');
		}
		print('</table>');
	}

	$returnto = htmlspecialchars((string) ($_SERVER["HTTP_REFERER"] ?? ''));
	if ($returnto) {
		print('<p><font size="small">(<a href="'.$returnto.'">'.$lang_comment['text_back'].'</a>)</font></p>');
	}
	stdfoot();
	die;
}
else
stderr($lang_comment['std_error'], $lang_comment['std_unknown_action']);

die;
?>
