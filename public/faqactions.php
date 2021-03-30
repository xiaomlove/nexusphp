<?php
/*
+--------------------------------------------------------------------------
|   MySQL driven FAQ version 1.1 Beta
|   ========================================
|   by avataru
|   (c) 2002 - 2005 avataru
|   http://www.avataru.net
|   ========================================
|   Web: http://www.avataru.net
|   Release: 1/9/2005 1:03 AM
|   Email: avataru@avataru.net
|   Tracker: http://www.sharereactor.ro
+---------------------------------------------------------------------------
|
|   > FAQ Management actions
|   > Written by avataru
|   > Date started: 1/7/2005
|
+--------------------------------------------------------------------------
*/

require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

if (get_user_class() < UC_ADMINISTRATOR) {
	stderr("Error","Only Administrators and above can modify the FAQ, sorry.");
}

//stdhead("FAQ Management");

// ACTION: reorder - reorder sections and items
if (isset($_GET['action']) && $_GET['action'] == "reorder") {
	foreach($_POST[order] as $id => $position) sql_query("UPDATE `faq` SET `order`=".sqlesc($position)." WHERE id=".sqlesc($id)) or sqlerr();
	header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
	die;
}

// ACTION: edit - edit a section or item
elseif (isset($_GET['action']) && $_GET['action'] == "edit" && isset($_GET['id'])) {
	stdhead("FAQ Management");
	begin_main_frame();
	print("<h1 align=\"center\">Edit Section or Item</h1>");

	$res = sql_query("SELECT * FROM faq WHERE id=".sqlesc($_GET['id'])." LIMIT 1");
	while ($arr = mysql_fetch_array($res, MYSQLI_BOTH)) {
		$arr['question'] = htmlspecialchars($arr['question']);
		$arr['answer'] = htmlspecialchars($arr['answer']);
		if ($arr['type'] == "item") {
			$lang_id = $arr['lang_id'];
			print("<form method=\"post\" action=\"faqactions.php?action=edititem\">");
			print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n");
			print("<tr><td>ID:</td><td>{$arr['id']} <input type=\"hidden\" name=\"id\" value=\"{$arr['id']}\" /></td></tr>\n");
			print("<tr><td>Question:</td><td><input style=\"width: 600px;\" type=\"text\" name=\"question\" value=\"{$arr['question']}\" /></td></tr>\n");
			print("<tr><td style=\"vertical-align: top;\">Answer:</td><td><textarea rows=20 style=\"width: 600px; height=600px;\" name=\"answer\">{$arr['answer']}</textarea></td></tr>\n");
			if ($arr['flag'] == "0") print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\" selected=\"selected\">Hidden</option><option value=\"1\" style=\"color: #000000;\">Normal</option><option value=\"2\" style=\"color: #0000FF;\">Updated</option><option value=\"3\" style=\"color: #008000;\">New</option></select></td></tr>");
			elseif ($arr['flag'] == "2") print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\">Hidden</option><option value=\"1\" style=\"color: #000000;\">Normal</option><option value=\"2\" style=\"color: #0000FF;\" selected=\"selected\">Updated</option><option value=\"3\" style=\"color: #008000;\">New</option></select></td></tr>");
			elseif ($arr['flag'] == "3") print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\">Hidden</option><option value=\"1\" style=\"color: #000000;\">Normal</option><option value=\"2\" style=\"color: #0000FF;\">Updated</option><option value=\"3\" style=\"color: #008000;\" selected=\"selected\">New</option></select></td></tr>");
			else print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\">Hidden</option><option value=\"1\" style=\"color: #000000;\" selected=\"selected\">Normal</option><option value=\"2\" style=\"color: #0000FF;\">Updated</option><option value=\"3\" style=\"color: #008000;\">New</option></select></td></tr>");
			print("<tr><td>Category:</td><td><select style=\"width: 400px;\" name=\"categ\" />");
			$res2 = sql_query("SELECT `id`, `question`, `link_id` FROM `faq` WHERE `type`='categ' AND `lang_id` = ".sqlesc($lang_id)." ORDER BY `order` ASC");
			while ($arr2 = mysql_fetch_array($res2, MYSQLI_BOTH)) {
				$selected = ($arr2['link_id'] == $arr['categ']) ? " selected=\"selected\"" : "";
				print("<option value=\"{$arr2['link_id']}\"". $selected .">{$arr2['question']}</option>");
			}
			print("</td></tr>\n");
			print("<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"edit\" value=\"Edit\" style=\"width: 60px;\"></td></tr>\n");
			print("</table>");
		}
		elseif ($arr['type'] == "categ") {
			$lang_res = sql_query("SELECT lang_name FROM language WHERE id=".sqlesc($arr['lang_id'])." LIMIT 1");
			if ($lang_arr = mysql_fetch_array($lang_res))
				$lang_name = $lang_arr['lang_name'];
			print("<form method=\"post\" action=\"faqactions.php?action=editsect\">");
			print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n");
			print("<tr><td>ID:</td><td>{$arr['id']} <input type=\"hidden\" name=\"id\" value=\"{$arr['id']}\" /></td></tr>\n");
			print("<tr><td>Language:</td><td>$lang_name</td></tr>\n");
			print("<tr><td>Title:</td><td><input style=\"width: 300px;\" type=\"text\" name=\"title\" value=\"{$arr['question']}\" /></td></tr>\n");
			if ($arr['flag'] == "0") print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\" selected=\"selected\">Hidden</option><option value=\"1\" style=\"color: #000000;\">Normal</option></select></td></tr>");
			else print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\">Hidden</option><option value=\"1\" style=\"color: #000000;\" selected=\"selected\">Normal</option></select></td></tr>");
			print("<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"edit\" value=\"Edit\" style=\"width: 60px;\"></td></tr>\n");
			print("</table>");
		}
	}

	end_main_frame();
	stdfoot();
}

// subACTION: edititem - edit an item
elseif (isset($_GET['action']) && $_GET['action'] == "edititem" && $_POST['id'] != NULL && $_POST['question'] != NULL && $_POST['answer'] != NULL && $_POST['flag'] != NULL && $_POST['categ'] != NULL) {
	$question = $_POST['question'];
	$answer = $_POST['answer'];
	sql_query("UPDATE `faq` SET `question`=".sqlesc($question).", `answer`=".sqlesc($answer).", `flag`=".sqlesc($_POST['flag']).", `categ`=".sqlesc($_POST['categ'])." WHERE id=".sqlesc($_POST['id'])) or sqlerr();
	header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
	die;
}

// subACTION: editsect - edit a section
elseif (isset($_GET['action']) && $_GET['action'] == "editsect" && $_POST['id'] != NULL && $_POST['title'] != NULL && $_POST['flag'] != NULL) {
	$title = $_POST['title'];
	sql_query("UPDATE `faq` SET `question`=".sqlesc($title).", `answer`='', `flag`=".sqlesc($_POST['flag']).", `categ`='0' WHERE id=".sqlesc($_POST['id'])) or sqlerr();
	header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
	die;
}

// ACTION: delete - delete a section or item
elseif (isset($_GET['action']) && $_GET['action'] == "delete" && isset($_GET['id'])) {
	if ($_GET[confirm] == "yes") {
		sql_query("DELETE FROM `faq` WHERE `id`=".sqlesc(intval($_GET['id'] ?? 0))." LIMIT 1") or sqlerr();
		header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
		die;
	}
	else {
		stdhead("FAQ Management");
		begin_main_frame();
		print("<h1 align=\"center\">Confirmation required</h1>");
		print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" align=\"center\" width=\"95%\">\n<tr><td align=\"center\">Please click <a href=\"faqactions.php?action=delete&id={$_GET['id']}&confirm=yes\">here</a> to confirm.</td></tr>\n</table>\n");
		end_main_frame();
		stdfoot();
	}
}

// ACTION: additem - add a new item
elseif (isset($_GET['action']) && $_GET['action'] == "additem" && $_GET['inid'] && $_GET['langid']) {
	stdhead("FAQ Management");
	begin_main_frame();
	print("<h1 align=\"center\">Add Item</h1>");
	print("<form method=\"post\" action=\"faqactions.php?action=addnewitem\">");
	print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n");
	print("<tr><td>Question:</td><td><input style=\"width: 600px;\" type=\"text\" name=\"question\" value=\"\" /></td></tr>\n");
	print("<tr><td style=\"vertical-align: top;\">Answer:</td><td><textarea rows=20 style=\"width: 600px; height=600px;\" name=\"answer\"></textarea></td></tr>\n");
	print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\">Hidden</option><option value=\"1\" style=\"color: #000000;\">Normal</option><option value=\"2\" style=\"color: #0000FF;\">Updated</option><option value=\"3\" style=\"color: #008000;\" selected=\"selected\">New</option></select></td></tr>");
	print("<input type=hidden name=categ value=\"".(intval($_GET['inid'] ?? 0))."\">");
	print("<input type=hidden name=langid value=\"".(intval($_GET['langid'] ?? 0))."\">");
	print("<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Add\" style=\"width: 60px;\"></td></tr>\n");
	print("</table></form>");
	end_main_frame();
	stdfoot();
}

// ACTION: addsection - add a new section
elseif (isset($_GET['action']) && $_GET['action'] == "addsection") {
	stdhead("FAQ Management");
	begin_main_frame();
	print("<h1 align=\"center\">Add Section</h1>");
	print("<form method=\"post\" action=\"faqactions.php?action=addnewsect\">");
	print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n");
	print("<tr><td>Title:</td><td><input style=\"width: 300px;\" type=\"text\" name=\"title\" value=\"\" /></td></tr>\n");
	$s = "<select name=language>";
	$langs = langlist("rule_lang");
	foreach ($langs as $row)
	{
		if($row["site_lang_folder"] == $deflang) $se = " selected"; else $se = "";
		$s .= "<option value=". $row["id"] . $se. ">" . htmlspecialchars($row["lang_name"]) . "</option>\n";
	}
	$s .= "</select>";
	print("<tr><td>Language:</td><td>".$s."</td></tr>");
	print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\">Hidden</option><option value=\"1\" style=\"color: #000000;\" selected=\"selected\">Normal</option></select></td></tr>");
	print("<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"edit\" value=\"Add\" style=\"width: 60px;\"></td></tr>\n");
	print("</table>");
	end_main_frame();
	stdfoot();
}

// subACTION: addnewitem - add a new item to the db
elseif (isset($_GET['action']) && $_GET['action'] == "addnewitem" && $_POST['question'] != NULL && $_POST['answer'] != NULL) {
	$question = $_POST['question'];
	$answer = $_POST['answer'];
	$categ = intval($_POST['categ'] ?? 0);
	$langid = intval($_POST['langid'] ?? 0);
	$res = sql_query("SELECT MAX(`order`) AS maxorder, MAX(`link_id`) AS maxlinkid FROM `faq` WHERE `type`='item' AND `categ`=".sqlesc($categ)." AND lang_id=".sqlesc($langid));
	while ($arr = mysql_fetch_array($res, MYSQLI_BOTH)) 
	{
		$order = $arr['maxorder'] + 1;
		$link_id = $arr['maxlinkid']+1;
	}
	sql_query("INSERT INTO `faq` (`link_id`, `type`, `lang_id`, `question`, `answer`, `flag`, `categ`, `order`) VALUES ('$link_id', 'item', ".sqlesc($langid).", ".sqlesc($question).", ".sqlesc($answer).", " . sqlesc(intval($_POST['flag'] ?? 0)) . ", ".sqlesc($categ).", ".sqlesc($order).")") or sqlerr();
	header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
	die;
}

// subACTION: addnewsect - add a new section to the db
elseif (isset($_GET['action']) && $_GET['action'] == "addnewsect" && $_POST['title'] != NULL && $_POST['flag'] != NULL) {
	$title = $_POST['title'];
	$language = intval($_POST['language'] ?? 0);
	$res = sql_query("SELECT MAX(`order`) AS maxorder, MAX(`link_id`) AS maxlinkid FROM `faq` WHERE `type`='categ' AND `lang_id` = ".sqlesc($language));
	while ($arr = mysql_fetch_array($res, MYSQLI_BOTH)) {$order = $arr['maxorder'] + 1;$link_id = $arr['maxlinkid']+1;}
	sql_query("INSERT INTO `faq` (`link_id`,`type`,`lang_id`, `question`, `answer`, `flag`, `categ`, `order`) VALUES (".sqlesc($link_id).",'categ', ".sqlesc($language).", ".sqlesc($title).", '', ".sqlesc($_POST['flag']).", '0', ".sqlesc($order).")") or sqlerr();
	header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
	die;
} else {
	header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
	die;
}
?>
