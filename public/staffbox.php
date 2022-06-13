<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
if (get_user_class() < $staffmem_class)
	permissiondenied();

$action = $_GET["action"] ?? '';

///////////////////////////
//        SHOW PM'S        //
/////////////////////////

if (!$action) {
	stdhead($lang_staffbox['head_staff_pm']);
	$url = $_SERVER['PHP_SELF']."?";
	$count = get_row_count("staffmessages");
	$perpage = 20;
	list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, $url);
	print ("<h1 align=center>".$lang_staffbox['text_staff_pm']."</h1>");
	if ($count == 0)
	{
		stdmsg($lang_staffbox['std_sorry'], $lang_staffbox['std_no_messages_yet']);
	}
	else
	{
		begin_main_frame();
		print("<form method=post action=\"?action=takecontactanswered\">");
		print("<table width=940 border=1 cellspacing=0 cellpadding=5 align=center>\n");
		print("<tr>
			<td class=colhead align=left>".$lang_staffbox['col_subject']."</td>
			<td class=colhead align=center>".$lang_staffbox['col_sender']."</td>
			<td class=colhead align=center><nobr>".$lang_staffbox['col_added']."</nobr></td>
			<td class=colhead align=center>".$lang_staffbox['col_answered']."</td>
			<td class=colhead align=center><nobr>".$lang_staffbox['col_action']."</nobr></td>
		</tr>");

	$res = sql_query("SELECT staffmessages.id, staffmessages.added, staffmessages.subject, staffmessages.answered, staffmessages.answeredby, staffmessages.sender, staffmessages.answer FROM staffmessages ORDER BY id desc $limit");

	while ($arr = mysql_fetch_assoc($res))
	{
    		if ($arr['answered'])
    		{
       			$answered = "<nobr><font color=green>".$lang_staffbox['text_yes']."</font> - " . get_username($arr['answeredby']) . "</nobr>";
    		}
   		else
			$answered = "<font color=red>".$lang_staffbox['text_no']."</font>";

    		$pmid = $arr["id"];
		print("<tr><td width=100% class=rowfollow align=left><a href=staffbox.php?action=viewpm&pmid=$pmid>".htmlspecialchars($arr['subject'])."</td><td class=rowfollow align=center>" . get_username($arr['sender']) . "</td><td class=rowfollow align=center><nobr>".gettime($arr['added'], true, false)."</nobr></td><td class=rowfollow align=center>$answered</td><td class=rowfollow align=center><input type=\"checkbox\" name=\"setanswered[]\" value=\"" . $arr['id'] . "\" /></td></tr>\n");
	}
	print("<tr><td class=rowfollow align=right colspan=5><input type=\"submit\" name=\"setdealt\" value=\"".$lang_staffbox['submit_set_answered']."\" /><input type=\"submit\" name=\"delete\" value=\"".$lang_staffbox['submit_delete']."\" /></td></tr>");
	print("</table>\n");
	print("</form>");
	echo $pagerbottom;
	end_main_frame();
	}
	stdfoot();
}

         //////////////////////////
        //        VIEW PM'S        //
       //////////////////////////

if ($action == "viewpm")
{
	if (get_user_class() < $staffmem_class)
		permissiondenied();

$pmid = intval($_GET["pmid"] ?? 0);

$ress4 = sql_query("SELECT * FROM staffmessages WHERE id=".sqlesc($pmid));
$arr4 = mysql_fetch_assoc($ress4);

$answeredby = get_username($arr4["answeredby"]);

if (is_valid_id($arr4["sender"]))
{
$sender = get_username($arr4["sender"]);
}
else
$sender = $lang_staffbox['text_system'];

$subject = htmlspecialchars($arr4["subject"]);
if ($arr4["answered"] == 1){
$colspan = "3";
$width = "33";
}
else{
$colspan = "2";
$width = "50";
}
stdhead($lang_staffbox['head_view_staff_pm']);
print("<h1 align=\"center\"><a class=\"faqlink\" href=\"staffbox.php\">".$lang_staffbox['text_staff_pm']."</a>-->".$subject."</h1>");
print("<table width=\"737\" border=\"0\" cellpadding=\"4\" cellspacing=\"0\">");
print("<tr><td width=\"".$width."%\" class=\"colhead\" align=\"left\">".$lang_staffbox['col_from']."</td>");
if ($arr4["answered"] == 1)
print("<td width=\"34%\" class=\"colhead\" align=\"left\">".$lang_staffbox['col_answered_by']."</td>");
print("<td width=\"".$width."%\" class=\"colhead\" align=\"left\">".$lang_staffbox['col_date']."</td></tr>");
print("<tr><td class=\"rowfollow\" align=\"left\">".$sender."</td>");
if ($arr4["answered"] == 1)
print("<td class=\"rowfollow\" align=\"left\">".$answeredby."</td>");
print("<td class=\"rowfollow\" align=\"left\">".gettime($arr4["added"])."</td></tr>");
print("<tr><td colspan=\"".$colspan."\" align=\"left\">".format_comment($arr4["msg"])."</td></tr>");
if ($arr4["answered"] == 1 && $arr4["answer"])
{
print("<tr><td colspan=\"".$colspan."\" align=\"left\">".format_comment($arr4["answer"])."</td></tr>");
}
print("<tr><td colspan=\"".$colspan."\" align=\"right\">");
print("<font color=white>");
if ($arr4["answered"] == 0)
print("[ <a href=\"staffbox.php?action=answermessage&receiver=" . $arr4['sender'] . "&answeringto=".$arr4['id']."\">".$lang_staffbox['text_reply']."</a> ] [ <a href=\"staffbox.php?action=setanswered&id=".$arr4['id']."\">".$lang_staffbox['text_mark_answered']."</a> ] ");
print("[ <a href=\"staffbox.php?action=deletestaffmessage&id=" . $arr4["id"] . "\">".$lang_staffbox['text_delete']."</a> ]");
print("</font>");
print("</td></tr>");
print("</table>");
stdfoot();
}
         //////////////////////////
        //        ANSWER MESSAGE        //
       //////////////////////////

if ($action == "answermessage") {
	if (get_user_class() < $staffmem_class)
		permissiondenied();

        $answeringto = $_GET["answeringto"];
        $receiver = intval($_GET["receiver"] ?? 0);

        int_check($receiver,true);

        $res = sql_query("SELECT * FROM users WHERE id=" . sqlesc($receiver));
        $user = mysql_fetch_assoc($res);

        if (!$user)
   		stderr($lang_staffbox['std_error'], $lang_staffbox['std_no_user_id']);

        $res2 = sql_query("SELECT * FROM staffmessages WHERE id=" . sqlesc($answeringto));
        $staffmsg = mysql_fetch_assoc($res2);
	stdhead($lang_staffbox['head_answer_to_staff_pm']);
	begin_main_frame();
        ?>
	<form method="post" id="compose" name="message" action="?action=takeanswer">
<?php if ($_GET["returnto"] || $_SERVER["HTTP_REFERER"]) { ?>
        <input type=hidden name=returnto value="<?php echo htmlspecialchars($_GET["returnto"] ?? '') ? htmlspecialchars($_GET["returnto"]) : htmlspecialchars($_SERVER["HTTP_REFERER"])?>">
<?php } ?>
        <input type=hidden name=receiver value=<?php echo $receiver?>>
        <input type=hidden name=answeringto value=<?php echo $answeringto?>>
<?php
	$title = $lang_staffbox['text_answering_to']."<a href=\"staffbox.php?action=viewpm&pmid=".$staffmsg['id']."\">".htmlspecialchars($staffmsg['subject'])."</a>".$lang_staffbox['text_sent_by'].get_username($staffmsg['sender']);
	begin_compose($title, "reply", "", false);
	end_compose();
	print("</form>");
	end_main_frame();
	stdfoot();
}

         //////////////////////////
        //        TAKE ANSWER        //
       //////////////////////////
if ($action == "takeanswer") {
  if ($_SERVER["REQUEST_METHOD"] != "POST")
    die();

    if (get_user_class() < $staffmem_class)
   permissiondenied();

     $receiver = intval($_POST["receiver"] ?? 0);
   $answeringto = $_POST["answeringto"];

   int_check($receiver,true);

          $userid = $CURUSER["id"];

   			$msg = trim($_POST["body"]);

          $message = sqlesc($msg);

          $added = "'" . date("Y-m-d H:i:s") . "'";

   if (!$msg)
     stderr($lang_staffbox['std_error'], $lang_staffbox['std_body_is_empty']);

sql_query("INSERT INTO messages (sender, receiver, added, msg) VALUES($userid, $receiver, $added, $message)") or sqlerr(__FILE__, __LINE__);

sql_query("UPDATE staffmessages SET answer=$message, answered='1', answeredby='$userid' WHERE id=$answeringto") or sqlerr(__FILE__, __LINE__);
$Cache->delete_value('staff_new_message_count');
        header("Location: staffbox.php?action=viewpm&pmid=$answeringto");
        die;
}
         //////////////////////////
        // DELETE STAFF MESSAGE        //
       //////////////////////////

if ($action == "deletestaffmessage") {

   $id = intval($_GET["id"] ?? 0);

    if (!is_numeric($id) || $id < 1 || floor($id) != $id)
    die;

          if (get_user_class() < $staffmem_class)
          permissiondenied();

    sql_query("DELETE FROM staffmessages WHERE id=" . sqlesc($id)) or die();
$Cache->delete_value('staff_message_count');
$Cache->delete_value('staff_new_message_count');
  header("Location: " . get_protocol_prefix() . "$BASEURL/staffbox.php");
}

         //////////////////////////
        // MARK AS ANSWERED        //
       //////////////////////////

if ($action == "setanswered") {

 if (get_user_class() < $staffmem_class)
    permissiondenied();

$id = intval($_GET["id"] ?? 0);

sql_query ("UPDATE staffmessages SET answered=1, answeredby = {$CURUSER['id']} WHERE id = $id") or sqlerr();
$Cache->delete_value('staff_new_message_count');
header("Refresh: 0; url=staffbox.php?action=viewpm&pmid=$id");
}

         //////////////////////////
        // MARK AS ANSWERED #2        //
       //////////////////////////

if ($action == "takecontactanswered") {
	if (get_user_class() < $staffmem_class)
		permissiondenied();

if ($_POST['setdealt']){
	$res = sql_query ("SELECT id FROM staffmessages WHERE answered=0 AND id IN (" . implode(", ", $_POST['setanswered']) . ")");
	while ($arr = mysql_fetch_assoc($res))
		sql_query ("UPDATE staffmessages SET answered=1, answeredby = {$CURUSER['id']} WHERE id = {$arr['id']}") or sqlerr();
}
elseif ($_POST['delete']){
	$res = sql_query ("SELECT id FROM staffmessages WHERE id IN (" . implode(", ", $_POST['setanswered']) . ")");
	while ($arr = mysql_fetch_assoc($res))
		sql_query ("DELETE FROM staffmessages WHERE id = {$arr['id']}") or sqlerr();
}
$Cache->delete_value('staff_new_message_count');
header("Refresh: 0; url=staffbox.php");
}

?>
