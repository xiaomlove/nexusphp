<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();
if (get_user_class() < UC_ADMINISTRATOR) {
	stderr("Error","Only Administrators and above can modify the Rules, sorry.");
}
function clear_rules_cache()
{
    \Nexus\Database\NexusDB::cache_del('rules');
}

if (isset($_GET["act"]) && $_GET["act"] == "newsect")
{
	stdhead("Add section");
	//print("<td valign=top style=\"padding: 10px;\" colspan=2 align=center>");
	//begin_main_frame();
	print("<h1 align=center>Add Rules</h1>");
	print("<form method=\"post\" action=\"modrules.php?act=addsect\">");
	print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n");
	print("<tr><td>Title:</td><td align=left><input style=\"width: 400px;\" type=\"text\" name=\"title\"/></td></tr>\n");
	print("<tr><td style=\"vertical-align: top;\">Rules:</td><td><textarea cols=90 rows=20 name=\"text\"></textarea></td></tr>\n");
	$s = "<select name=language>";
	$langs = langlist("rule_lang");
	foreach ($langs as $row)
	{
		if($row["site_lang_folder"] == $deflang) $se = " selected"; else $se = "";
		$s .= "<option value=". $row["id"] . $se. ">" . htmlspecialchars($row["lang_name"]) . "</option>\n";
	}
	$s .= "</select>";
	print("<tr><td>Language:</td><td align=\"center\">".$s."</td></tr>\n");
	print("<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Add\" style=\"width: 60px;\"></td></tr>\n");
	print("</table></form>");
	print("</td></tr></table>");
	stdfoot();
}
elseif (isset($_GET["act"]) && $_GET["act"]=="addsect"){
	$title = $_POST["title"];
	$text = $_POST["text"];
	$language = $_POST["language"];
	\Nexus\Database\NexusDB::insert('rules', [
		'title' => (string) $title,
		'text' => (string) $text,
		'lang_id' => (int) $language,
	]);
    clear_rules_cache();
	header("Location: modrules.php");
}
elseif (isset($_GET["act"]) && $_GET["act"] == "edit"){
	$id = intval($_GET["id"]);
	$resObj = \Nexus\Database\NexusDB::table('rules')->where('id', (int) $id)->first();
	$res = $resObj ? (array) $resObj : [];
	stdhead("Edit rules");
	//print("<td valign=top style=\"padding: 10px;\" colspan=2 align=center>");
	//begin_main_frame();
	print("<h1 align=center>Edit Rules</h1>");
	print("<form method=\"post\" action=\"modrules.php?act=edited\">");
	print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n");
	print("<tr><td>Title:</td><td align=left><input style=\"width: 400px;\" type=\"text\" name=\"title\" value=\"".htmlspecialchars($res['title'])."\" /></td></tr>\n");
	print("<tr><td style=\"vertical-align: top;\">Rules:</td><td><textarea cols=90 rows=20 name=\"text\">{$res['text']}</textarea></td></tr>\n");
	$s = "<select name=language>";
	$langs = langlist("site_lang");
	foreach ($langs as $row)
	{
		if ($row['id'] == $res['lang_id']) $se = " selected"; else $se = "";
		$s .= "<option value=". $row["id"] . $se. ">" . htmlspecialchars($row["lang_name"]) . "</option>\n";
	}
	$s .= "</select>";
	print("<tr><td>Language:</td><td align=\"center\">".$s."</td></tr>\n");
	print("<tr><td colspan=\"2\" align=\"center\"><input type=hidden value=$res[id] name=id><input type=\"submit\" value=\"Save\" style=\"width: 60px;\"></td></tr>\n");
	print("</table>");
	print("</td></tr></table>");
	stdfoot();
}
elseif (isset($_GET["act"]) && $_GET["act"]=="edited"){
	$id = intval($_POST["id"] ?? 0);
	$title = $_POST["title"];
	$text = $_POST["text"];
	$language = $_POST["language"];
	\Nexus\Database\NexusDB::table('rules')->where('id', (int) $id)->update([
		'title' => (string) $title,
		'text' => (string) $text,
		'lang_id' => (int) $language,
	]);
    clear_rules_cache();
	header("Location: modrules.php");
}
elseif (isset($_GET["act"]) && $_GET["act"]=="del"){
	$id = (int)$_GET["id"];
	$sure = intval($_GET["sure"] ?? 0);
	if (!$sure)
	{
		stderr("Delete Rule","You are about to delete a rule. Click <a class=altlink href=?act=del&id=$id&sure=1>here</a> if you are sure.",false);
	}
	\Nexus\Database\NexusDB::table('rules')->where('id', (int) $id)->delete();
    clear_rules_cache();
	header("Location: modrules.php");
}
else{
	$ruleRows = \Nexus\Database\NexusDB::select("select rules.*, lang_name from rules left join language on rules.lang_id = language.id order by lang_name, id");
	stdhead("Rules Manangement");
	//print("<td valign=top style=\"padding: 10px;\" colspan=2 align=center>");
	print("<h1 align=center>Rules Manangement</h1>");
	print("<br /><table width=940 border=0 cellspacing=0 cellpadding=5>");
	print("<tr><td align=center><a href=modrules.php?act=newsect>Add Section</a></td></tr></table>\n");
	foreach ($ruleRows as $arr){
		$arr = (array) $arr;
		print("<br /><table width=940 border=1 cellspacing=0 cellpadding=5>");
		print("<tr><td class=colhead>$arr[title] - $arr[lang_name]</td></tr>\n");
		print("<tr><td align=left>" . format_comment($arr["text"])."</td></tr>");
		print("<tr><td align=left><a href=?act=edit&id=$arr[id]>Edit</a>&nbsp;&nbsp;<a href=?act=del&id=$arr[id]>Delete</a></td></tr></table>");
		//end_main_frame();
	}
	//print("");
	print("</td></tr></table>");
	stdfoot();
}
