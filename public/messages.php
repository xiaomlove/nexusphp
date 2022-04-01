<?php
require_once("../include/bittorrent.php");
// Connect to DB & check login
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
// Define constants
define('PM_DELETED',0); // Message was deleted
define('PM_INBOX',1); // Message located in Inbox for reciever
define('PM_SENTBOX',-1); // GET value for sent box
// Determine action
$action = $_GET['action'] ?? '';
if (!$action)
{
	$action = $_POST['action'] ?? '';
	if (!$action)
		$action = 'viewmailbox';
}

// View listing of Messages in mail box
if ($action == "viewmailbox")
{
// Get Mailbox Number
$mailbox = $_GET['box'] ?? 0;
if (!$mailbox)
	$mailbox = PM_INBOX;

// Get Mailbox Name
if ($mailbox != PM_INBOX && $mailbox != PM_SENTBOX)
{
$res = sql_query('SELECT name FROM pmboxes WHERE userid=' . sqlesc($CURUSER['id']) . ' AND boxnumber=' . sqlesc($mailbox) . ' LIMIT 1') or sqlerr(__FILE__,__LINE__);
if (mysql_num_rows($res) == 0)
	stderr($lang_messages['std_error'],$lang_messages['std_invalid_mailbox']);

$mailbox_name = mysql_fetch_array($res);
$mailbox_name = htmlspecialchars($mailbox_name[0]);
}
else
{
if ($mailbox == PM_INBOX)
	$mailbox_name = $lang_messages['text_inbox'];
else
	$mailbox_name = $lang_messages['text_sentbox'];
}

if ($mailbox != PM_SENTBOX)
	$sender_receiver = $lang_messages['text_sender'];
else
	$sender_receiver = $lang_messages['text_receiver'];
// Start Page
stdhead($mailbox_name);
?>
<?php messagemenu($mailbox)?>
<table border="0" cellpadding="4" cellspacing="0" width="737">
<tr><td class=colhead align=left><?php echo $lang_messages['col_search_message'] ?></td></tr>
<tr><td class=toolbox align=center><?php echo insertJumpTo($mailbox);?></td></tr>
</table>

<?php
//search
		$keyword = mysql_real_escape_string(trim($_GET["keyword"] ?? ''));
		$place = $_GET["place"] ?? '';
		if($keyword)
			switch ($place){
				case "body": $wherea=" AND msg LIKE '%$keyword%' "; break;
				case "title": $wherea=" AND subject LIKE '%$keyword%' "; break;
				case "both": $wherea=" AND (msg LIKE '%$keyword%' or subject LIKE '%$keyword%') "; break;
				default: $wherea=" AND (msg LIKE '%$keyword%' or subject LIKE '%$keyword%') "; break;
				}
		else
		$wherea="";
		$unread=$_GET["unread"] ?? '';
		if ($unread)
			switch ($unread){
				case "yes": $wherea.=" AND unread = 'yes' "; break;
				case "no": $wherea.=" AND unread = 'no' "; break;
				}
if ($mailbox != PM_SENTBOX)
{
		$res = sql_query('SELECT COUNT(*) FROM messages WHERE receiver=' . sqlesc($CURUSER['id']) . ' AND location=' . sqlesc($mailbox).$wherea);
		$row = mysql_fetch_array($res);
		$count = $row[0];

		$perpage = ($CURUSER['pmnum'] ? $CURUSER['pmnum'] : 20);

		list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "?action=viewmailbox".($mailbox ? "&box=".$mailbox : "").($place ? "&place=".$place : "").($keyword ? "&keyword=".rawurlencode($keyword) : "").($unread ? "&unread=".$unread : "")."&");
$res = sql_query('SELECT * FROM messages WHERE receiver=' . sqlesc($CURUSER['id']) . ' AND location=' . sqlesc($mailbox) .$wherea. ' ORDER BY id DESC '.$limit) or

sqlerr(__FILE__,__LINE__);
}
else
{
		$res = sql_query('SELECT COUNT(*) FROM messages WHERE sender=' . sqlesc($CURUSER['id']) . ' AND saved=\'yes\''.$wherea);
		$row = mysql_fetch_array($res);
		$count = $row[0];

		$perpage = ($CURUSER['pmnum'] ? $CURUSER['pmnum'] : 20);

		list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "?action=viewmailbox".($mailbox ? "&box=".$mailbox : "").($place ? "&place=".$place : "").($keyword ? "&keyword=".rawurlencode($keyword) : "").($unread ? "&unread=".$unread : "")."&");
$res = sql_query('SELECT * FROM messages WHERE sender=' . sqlesc($CURUSER['id']) . ' AND saved=\'yes\''.$wherea.' ORDER BY id DESC '.$limit) or sqlerr(__FILE__,__LINE__);
}

if (mysql_num_rows($res) == 0)
{
echo("<p align=\"center\">".$lang_messages['text_no_messages']."</p>\n");
}
else
{
echo $pagertop;
?>
<form action="messages.php" method="post">
<input type="hidden" name="action" value="moveordel">
<table border="0" cellpadding="4" cellspacing="0" width="737">
<tr>
<td width="1%" class="colhead" align="center"><?php echo $lang_messages['col_status'] ?></td>
<td class="colhead" align="left"><?php echo $lang_messages['col_subject'] ?> </td>
<?php
print("<td width=\"35%\" class=\"colhead\" align=\"left\">$sender_receiver</td>");
?>
<td width="1%" class="colhead" align="center"><img class="time" src="pic/trans.gif" alt="time" title="<?php echo $lang_messages['col_date'] ?>" /></td>
<td width="1%" class="colhead" align="center"><?php echo $lang_messages['col_act'] ?></td>
</tr>
<?php
while ($row = mysql_fetch_assoc($res))
{
// Get Sender Username
if ($row['sender'] != 0)
{
if ($mailbox != PM_SENTBOX)
	$username = get_username($row['sender']);
else
	$username = get_username($row['receiver']);
}
else
{
$username = $lang_messages['text_system'];
}
$subject = htmlspecialchars($row['subject']);

if (strlen($subject) <= 0)
{
$subject = $lang_messages['text_no_subject'];
}

if ($row['unread'] == 'yes')
{
echo("<tr>\n<td class=rowfollow align=center><img class=\"unreadpm\" src=\"pic/trans.gif\" alt=\"Unread\" title=".$lang_messages['title_unread']." /></td>\n");
}
else
{
echo("<tr>\n<td class=rowfollow align=center><img class=\"readpm\" src=\"pic/trans.gif\" alt=\"Read\" title=".$lang_messages['title_read']." /></td>\n");
}
echo("<td class=rowfollow align=left><a href=\"messages.php?action=viewmessage&id=" . $row['id'] . "\">" .
$subject . "</a></td>\n");
echo("<td class=rowfollow align=left>$username</td>\n");
echo("<td class=rowfollow nowrap>" . gettime($row['added'],true,false) . "</td>\n");
echo("<td class=rowfollow><input class=checkbox type=\"checkbox\" name=\"messages[]\" value=\"" . $row['id'] . "\"></td>\n</tr>\n");
}
?>
<tr class="colhead">
<td colspan="5" align="right" class="colhead"><input class=btn type="button" value="<?php echo $lang_messages['input_check_all']; ?>" onClick="this.value=check(form,'<?php echo $lang_messages['input_check_all'] ?>','<?php echo $lang_messages['input_uncheck_all'] ?>')">
<?php if($mailbox != PM_SENTBOX) print("<input class=btn type=\"submit\" name=\"markread\" value=\"".$lang_messages['submit_mark_as_read']."\">") ?>
<input class=btn type="submit" name="delete" value=<?php echo $lang_messages['submit_delete']?>>
<?php
if($mailbox != PM_SENTBOX){
	echo $lang_messages['text_or'];
	print("<input class=btn type=\"submit\" name=\"move\" value=\"".$lang_messages['submit_move_to']."\"> <select name=\"box\"><option value=\"1\">".$lang_messages['text_inbox']."</option>");
        $res = sql_query('SELECT * FROM pmboxes WHERE userid=' . sqlesc($CURUSER['id']) . ' ORDER BY boxnumber') or sqlerr(__FILE__,__LINE__);
        while ($row = mysql_fetch_assoc($res))
        {
          echo("<option value=\"" . $row['boxnumber'] . "\">" . htmlspecialchars($row['name']) . "</option>\n");
        }
}
?>
      <?php /*
      print("<p align=right><input type=button value=\"Check All\" onClick=\"this.value=check(form)\"><input type=submit value=\"Delete selected\"></p>");
print("</form>");
     */ ?>
        </select>
      </td>
    </tr>

  </form><tr><td class=toolbox colspan=5>
<div align="center"><img class="unreadpm" src="pic/trans.gif" alt="Unread" title="<?php echo $lang_messages['title_unread'] ?>" /><a href="messages.php?action=viewmailbox&box=<?php echo $mailbox?>&unread=yes"><?php echo $lang_messages['text_unread_messages'] ?></a>
<img class="readpm" src="pic/trans.gif" alt="Read" title="<?php echo $lang_messages['title_read'] ?>" /><a href="messages.php?action=viewmailbox&box=<?php echo $mailbox?>&unread=no"><?php echo $lang_messages['text_read_messages'] ?></a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<a href="messages.php?action=editmailboxes"><b><?php echo $lang_messages['text_mailbox_manager'] ?></a></b></div></td></tr></table>
<?php
}
stdfoot();
}
if ($action == "viewmessage")
{
$pm_id = (int) $_GET['id'];
if (!$pm_id)
{
stderr($lang_messages['std_error'],$lang_messages['std_no_permission']);
}

// Get the message
$res = sql_query('SELECT * FROM messages WHERE id=' . sqlesc($pm_id) . ' AND (receiver=' . sqlesc($CURUSER['id']) . ' OR (sender=' . sqlesc($CURUSER['id'])

. ' AND saved=\'yes\')) LIMIT 1') or sqlerr(__FILE__,__LINE__);
if (!$res)
{
	stderr($lang_messages['std_error'],$lang_messages['std_no_permission']);
}

// Prepare for displaying message
$message = mysql_fetch_assoc($res) or header("Location: messages.php");
if ($message['sender'] == $CURUSER['id'])
{
// Display to
$sender = get_username($message['receiver']);
$reply = "";
$from = $lang_messages['text_to'];
}
else
{
$from = $lang_messages['text_from'];
if ($message['sender'] == 0)
{
$sender = $lang_messages['text_system'];
$reply = "";
}
else
{
$sender = get_username($message['sender']);
$reply = " [ <a href=\"sendmessage.php?receiver=" . $message['sender'] . "&replyto=" . $pm_id . "\">".$lang_messages['text_reply']."</a> ]";
}
}
$body = format_comment($message['msg']);
$body = htmlspecialchars_decode($body);
$added = $message['added'];
if ($message['sender'] == $CURUSER['id'])
{
$unread = ($message['unread'] == 'yes' ? "<span style=\"color: #FF0000;\"><b>".$lang_messages['text_new']."</b></a>" : "");
}
else
{
$unread = "";
}
$subject = htmlspecialchars($message['subject']);
if (strlen($subject) <= 0)
{
$subject = $lang_messages['text_no_subject'];
}

// Mark message unread
sql_query("UPDATE messages SET unread='no' WHERE id=" . sqlesc($pm_id) . " AND receiver=" . sqlesc($CURUSER['id']) . " LIMIT 1");
$Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
// Display message
stdhead("PM ($subject)"); ?>
<h1><?php echo $subject?></h1>
<?php
$mailbox = ($message['sender'] == $CURUSER['id'] ? -1 : $message['location']);
messagemenu($mailbox);
?>
<table width="737" border="0" cellpadding="4" cellspacing="0">
<tr>
<td width="50%" class="colhead" align="left"><?php echo $from?></td>
<td width="50%" class="colhead" align="left"><?php echo $lang_messages['col_date'] ?></td>
</tr>
<tr>
<td class="rowfollow" align="left"><?php echo $sender?></td>
<td class="rowfollow" align="left"><?php echo gettime($added,true,false)?>&nbsp;&nbsp;<?php echo $unread?></td>
</tr>
<tr>
<td colspan="2" align="left"><?php echo $body?></td>
</tr>
<tr>
<td align=left>
<?php if($message['sender'] != $CURUSER['id']){
print("<form action=\"messages.php\" method=\"post\"><input type=\"hidden\" name=\"action\" value=\"moveordel\"><input type=\"hidden\" name=\"id\" value=".$pm_id.">
<input type=\"submit\" name=\"move\" value=".$lang_messages['submit_move_to']."><select name=\"box\"><option value=\"1\">".$lang_messages['text_inbox']."</option>");
$res = sql_query('SELECT * FROM pmboxes WHERE userid=' . sqlesc($CURUSER['id']) . ' ORDER BY boxnumber') or sqlerr(__FILE__,__LINE__);
while ($row = mysql_fetch_assoc($res))
{
echo("<option value=\"" . $row['boxnumber'] . "\">" . htmlspecialchars($row['name']) . "</option>\n");
}
print("</select></form>");
}
?>
</td><td align="right" ><font color=white>[ <a href="messages.php?action=deletemessage&id=<?php echo $pm_id?>"><?php echo $lang_messages['text_delete'] ?></a> ]<?php echo $reply?> [ <a

href="messages.php?action=forward&id=<?php echo $pm_id?>"><?php echo $lang_messages['text_forward_pm'] ?></a> ]</font></td>
</tr>
</table>
<?php
stdfoot();
}
if ($action == "moveordel")
{
$pm_id = (int) $_POST['id'];
$pm_box = (int) $_POST['box'];
$pm_messages = $_POST['messages'];
if ($_POST['markread'])
{
	if ($pm_id)
	{
//Mark a single message as read
	@sql_query("UPDATE messages SET unread='no' WHERE id=" . sqlesc($pm_id) . " AND receiver=" . $CURUSER['id'] . " LIMIT 1");
	}
	else
	{
// Mark multiple messages as read
	@sql_query("UPDATE messages SET unread='no' WHERE id IN (" . implode(", ", array_map("sqlesc",$pm_messages)) . ") AND receiver=" .$CURUSER['id']);
	}
	$Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
// Check if messages were moved
	if (@mysql_affected_rows() == 0)
	{
	stderr($lang_messages['std_error'],$lang_messages['std_cannot_mark_messages']);
	}

	header("Location: messages.php?action=viewmailbox&box=" . $pm_box);
	exit();
}
elseif ($_POST['move'])
{
if ($pm_id)
{
// Move a single message
@sql_query("UPDATE messages SET location=" . sqlesc($pm_box) . " WHERE id=" . sqlesc($pm_id) . " AND receiver=" . $CURUSER['id'] . " LIMIT 1");

}
else
{
// Move multiple messages
@sql_query("UPDATE messages SET location=" . sqlesc($pm_box) . " WHERE id IN (" . implode(", ", array_map("sqlesc",$pm_messages)) . ') AND receiver=' .$CURUSER['id']);
}
// Check if messages were moved
if (@mysql_affected_rows() == 0)
{
stderr($lang_messages['std_error'],$lang_messages['std_cannot_move_messages']);
}
	$Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
	$Cache->delete_value('user_'.$CURUSER['id'].'_inbox_count');
	$Cache->delete_value('user_'.$CURUSER["id"].'_outbox_count');
header("Location: messages.php?action=viewmailbox&box=" . $pm_box);
exit();
}
elseif ($_POST['delete'])
{
if ($pm_id)
{
// Delete a single message
$res = sql_query("SELECT * FROM messages WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
$message = mysql_fetch_assoc($res);
if ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'no')
{
	sql_query("DELETE FROM messages WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
	$Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
	$Cache->delete_value('user_'.$CURUSER['id'].'_inbox_count');
}
elseif ($message['sender'] == $CURUSER['id'] && $message['location'] == PM_DELETED)
{
	sql_query("DELETE FROM messages WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
	$Cache->delete_value('user_'.$CURUSER["id"].'_outbox_count');
}
elseif ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'yes')
{
	sql_query("UPDATE messages SET location=0, unread = 'no' WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
	$Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
	$Cache->delete_value('user_'.$CURUSER['id'].'_inbox_count');
}
elseif ($message['sender'] == $CURUSER['id'] && $message['location'] != PM_DELETED)
{
	sql_query("UPDATE messages SET saved='no' WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
	$Cache->delete_value('user_'.$CURUSER["id"].'_outbox_count');
}
}
else
{
if (!$pm_messages)
stderr($lang_messages['std_error'], $lang_messages['std_no_message_selected']);
// Delete multiple messages
foreach ($pm_messages as $id)
{
$res = sql_query("SELECT * FROM messages WHERE id=" . sqlesc((int) $id));
$message = mysql_fetch_assoc($res);
if ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'no')
{
sql_query("DELETE FROM messages WHERE id=" . sqlesc((int) $id)) or sqlerr(__FILE__,__LINE__);
}
elseif ($message['sender'] == $CURUSER['id'] && $message['location'] == PM_DELETED)
{
sql_query("DELETE FROM messages WHERE id=" . sqlesc((int) $id)) or sqlerr(__FILE__,__LINE__);
}
elseif ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'yes')
{
sql_query("UPDATE messages SET location=0, unread = 'no' WHERE id=" . sqlesc((int) $id)) or sqlerr(__FILE__,__LINE__);
}
elseif ($message['sender'] == $CURUSER['id'] && $message['location'] != PM_DELETED)
{
sql_query("UPDATE messages SET saved='no' WHERE id=" . sqlesc((int) $id)) or sqlerr(__FILE__,__LINE__);
}
}
	$Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
	$Cache->delete_value('user_'.$CURUSER['id'].'_inbox_count');
	$Cache->delete_value('user_'.$CURUSER["id"].'_outbox_count');
}
// Check if messages were moved
if (@mysql_affected_rows() == 0)
{
stderr($lang_messages['std_error'],$lang_messages['std_cannot_delete_messages']);
}
else
{
header("Location: messages.php?action=viewmailbox");
exit();
}
}
stderr($lang_messages['std_error'],$lang_messages['std_no_action']);
}


if ($action == "forward")
{
// Display form
$pm_id = (int) $_GET['id'];

// Get the message
$res = sql_query('SELECT * FROM messages WHERE id=' . sqlesc($pm_id) . ' AND (receiver=' . sqlesc($CURUSER['id']) . ' OR sender=' . sqlesc($CURUSER['id']) .') LIMIT 1') or sqlerr(__FILE__,__LINE__);
if (!$res)
{
stderr($lang_messages['std_error'],$lang_messages['std_no_permission_forwarding']);
}
if (mysql_num_rows($res) == 0)
{
stderr($lang_messages['std_error'],$lang_messages['std_no_permission_forwarding']);
}
$message = mysql_fetch_assoc($res);

// Prepare variables
$subject = "Fwd: " . htmlspecialchars($message['subject']);
$from = $message['receiver'];
$orig = $message['sender'];

$from_name = get_username($from);
if ($orig == 0)
{
$orig_name = $orig_name2 = $lang_messages['text_system'];
}
else
{
$orig_name = get_username($orig);
$res = sql_query("SELECT username FROM users WHERE id=" . sqlesc($orig)) or sqlerr(__FILE__,__LINE__);
$orig_nameres = mysql_fetch_array($res);
$orig_name2 = $orig_nameres['username'];
}

$body = "-------- Original Message from " . $orig_name2 . " --------<br />" . format_comment($message['msg']);

stdhead($subject);?>
<h1 align="center"><?php echo $lang_messages['text_forward_pm'] ?></h1>
<table border="0" cellpadding="4" cellspacing="0"  width="737">
<form action="takemessage.php" method="post">
<input type="hidden" name="forward" value="1">
<input type="hidden" name="origmsg" value="<?php echo $pm_id?>">
<tr>
<td class="rowhead" align="right"><?php echo $lang_messages['row_to'] ?></td>
<td class="rowfollow" align=left><input type="text" name="to" style="width: 200px"></td>
</tr>
<tr>
<td class="rowhead" align="right"><?php echo $lang_messages['row_original_receiver'] ?></td>
<td class="rowfollow" align=left><?php echo $from_name?></td>
</tr>
<tr>
<td class="rowhead" align="right"><?php echo $lang_messages['row_original_sender'] ?></td>
<td class="rowfollow" align=left><?php echo $orig_name?></td>
</tr>
<tr>
<td class="rowhead" align="right"><?php echo $lang_messages['row_subject'] ?></td>
<td class="rowfollow" align=left><input type="text" name="subject" value="<?php echo $subject?>" style="width: 500px"></td>
</tr>
<tr>
<td class="rowhead" align="right" valign="top"><nobr><?php echo $lang_messages['row_message'] ?></nobr></td>
<td class="rowfollow" align=left><textarea name="body" style="width: 500px" rows="8"></textarea><br /><?php echo $body?></td>
</tr>
<tr>
<td class=toolbox colspan="2" align="center"><input class=checkbox type="checkbox" name="save" value="yes"<?php echo $CURUSER['savepms'] == 'yes'?" checked":""?>><?php echo $lang_messages['checkbox_save_message'] ?>&nbsp;
<input type="submit" class="btn" value=<?php echo $lang_messages['submit_forward']?>></td>
</tr>
</table>
</form>
<?php
stdfoot();
}
if ($action == "editmailboxes")
{
$res = sql_query("SELECT * FROM pmboxes WHERE userid=" . sqlesc($CURUSER['id'])) or sqlerr(__FILE__,__LINE__);

stdhead($lang_messages['head_editing_mailboxes']); ?>
<h1><?php echo $lang_messages['text_editing_mailboxes'] ?></h1>
<table width="737" border="0" cellpadding="4" cellspacing="0">
<tr>
<td class="colhead" align="left"><?php echo $lang_messages['text_add_mailboxes'] ?></td>
</tr>
<tr>
<td align=left><?php echo $lang_messages['text_extra_mailboxes_note'] ?><br />
<form action="messages.php" method="get">
<input type="hidden" name="action" value="editmailboxes2">
<input type="hidden" name="action2" value="add">

<input type="text" name="new1" size="40" maxlength="14"><br />
<input type="text" name="new2" size="40" maxlength="14"><br />
<input type="text" name="new3" size="40" maxlength="14"><br />
<input type="submit" value="<?php echo $lang_messages['submit_add'] ?>">
</form></td>
</tr>
<tr>
<td class="colhead" align=left><?php echo $lang_messages['text_edit_mailboxes'] ?></td>
</tr>
<tr>
<td align=left><?php echo $lang_messages['text_edit_mailboxes_note'] ?>
<form action="messages.php" method="get">
<input type="hidden" name="action" value="editmailboxes2">
<input type="hidden" name="action2" value="edit">
<?php
if (!$res)
{
echo ("<span align=\"center\"><b>".$lang_messages['text_no_mailboxes_to_edit']."<b></span>");
}
if (mysql_num_rows($res) == 0)
{
echo ("<span align=\"center\"><b>".$lang_messages['text_no_mailboxes_to_edit']."</b></span>");
}
else
{
while ($row = mysql_fetch_assoc($res))
{
$id = $row['id'];
$name = htmlspecialchars($row['name']);
echo("<input type=\"text\" name=\"edit$id\" value=\"$name\" size=\"40\" maxlength=\"14\"><br />\n");
}
echo("<input type=\"submit\" value=".$lang_messages['submit_edit'].">");
}
?></form></td>
</tr>
</table>
<?php
stdfoot();
}
if ($action == "editmailboxes2")
{
$action2 = (string) $_GET['action2'];
if (!$action2)
{
stderr($lang_messages['std_error'],$lang_messages['std_no_action']);
}
if ($action2 == "add")
{
$nameone = $_GET['new1'];
$nametwo = $_GET['new2'];
$namethree = $_GET['new3'];

// Get current max box number
$res = sql_query("SELECT MAX(boxnumber) FROM pmboxes WHERE userid=" . sqlesc($CURUSER['id']));
$box = mysql_fetch_array($res);
$box = (int) $box[0];
if ($box < 2)
{
$box = 1;
}
if (strlen($nameone) > 0)
{
++$box;
sql_query("INSERT INTO pmboxes (userid, name, boxnumber) VALUES (" . sqlesc($CURUSER['id']) . ", " . sqlesc($nameone) . ", $box)") or sqlerr(__FILE__,__LINE__);
}
if (strlen($nametwo) > 0)
{
++$box;
sql_query("INSERT INTO pmboxes (userid, name, boxnumber) VALUES (" . sqlesc($CURUSER['id']) . ", " . sqlesc($nametwo) . ", $box)") or sqlerr(__FILE__,__LINE__);
}
if (strlen($namethree) > 0)
{
++$box;
sql_query("INSERT INTO pmboxes (userid, name, boxnumber) VALUES (" . sqlesc($CURUSER['id']) . ", " . sqlesc($namethree) . ", $box)") or sqlerr(__FILE__,__LINE__);
}
header("Location: messages.php?action=editmailboxes");
exit();
}
if ($action2 == "edit");
{
$res = sql_query("SELECT * FROM pmboxes WHERE userid=" . sqlesc($CURUSER['id']));
if (!$res)
{
stderr($lang_messages['std_error'],$lang_messages['text_no_mailboxes_to_edit']);
}
if (mysql_num_rows($res) == 0)
{
stderr($lang_messages['std_error'],$lang_messages['text_no_mailboxes_to_edit']);
}
else
{
while ($row = mysql_fetch_assoc($res))
{
if (isset($_GET['edit' . $row['id']]))
{
if ($_GET['edit' . $row['id']] != $row['name'])
{
// Do something
if (strlen($_GET['edit' . $row['id']]) > 0)
{
// Edit name
sql_query("UPDATE pmboxes SET name=" . sqlesc($_GET['edit' . $row['id']]) . " WHERE id=" . sqlesc($row['id']) . " LIMIT 1");
}
else
{
// Delete
sql_query("DELETE FROM pmboxes WHERE id=" . sqlesc($row['id']) . " LIMIT 1");
// Delete all messages from this folder (uses multiple queries because we can only perform security checks in WHERE clauses)
sql_query("UPDATE messages SET location=0 WHERE saved='yes' AND location=" . sqlesc($row['boxnumber']) . " AND receiver=" . sqlesc($CURUSER['id']));
sql_query("UPDATE messages SET saved='no' WHERE saved='yes' AND sender=" . sqlesc($CURUSER['id']));
sql_query("DELETE FROM messages WHERE saved='no' AND location=" . sqlesc($row['boxnumber']) . " AND receiver=" . sqlesc($CURUSER['id']));
sql_query("DELETE FROM messages WHERE location=0 AND saved='yes' AND sender=" . sqlesc($CURUSER['id']));
}
}
}
}
header("Location: messages.php?action=editmailboxes");
exit();
}
}
}
if ($action == "deletemessage")
{
$pm_id = (int) $_GET['id'];

// Delete message
$res = sql_query("SELECT * FROM messages WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
if (!$res)
{
stderr($lang_messages['std_error'],$lang_messages['std_no_message_id']);
}
if (mysql_num_rows($res) == 0)
{
stderr($lang_messages['std_error'],$lang_messages['std_no_message_id']);
}
$message = mysql_fetch_assoc($res);
if ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'no')
{
$res2 = sql_query("DELETE FROM messages WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
}
elseif ($message['sender'] == $CURUSER['id'] && $message['location'] == PM_DELETED)
{
$res2 = sql_query("DELETE FROM messages WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
}
elseif ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'yes')
{
$res2 = sql_query("UPDATE messages SET location=0 WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
}
elseif ($message['sender'] == $CURUSER['id'] && $message['location'] != PM_DELETED)
{
$res2 = sql_query("UPDATE messages SET saved='no' WHERE id=" . sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
}
if (!$res2)
{
stderr($lang_messages['std_error'],$lang_messages['std_could_not_delete_message']);
}
if (mysql_affected_rows() == 0)
{
stderr($lang_messages['std_error'],$lang_messages['std_could_not_delete_message']);
}
else
{
header("Location: messages.php?action=viewmailbox&id=" . $message['location']);
exit();
}
}

//----- FUNCTIONS ------
function insertJumpTo($selected = 0)
{
global $lang_messages;
global $CURUSER;
$res = sql_query('SELECT * FROM pmboxes WHERE userid=' . sqlesc($CURUSER['id']) . ' ORDER BY boxnumber');
$place = $_GET['place'] ?? '';
?>
<form action="messages.php" method="get">
<input type="hidden" name="action" value="viewmailbox"><?php echo $lang_messages['text_search'] ?>&nbsp;&nbsp;<input id="searchinput" name="keyword" type="text" value="<?php echo htmlspecialchars($_GET['keyword'] ?? '')?>" style="width: 200px"/>
<?php echo $lang_messages['text_in'] ?>&nbsp;<select name="place">
<option value="both" <?php echo ($place == 'both' ? " selected" : "")?>><?php echo $lang_messages['select_both'] ?></option>
<option value="title" <?php echo ($place == 'title' ? " selected" : "")?>><?php echo $lang_messages['select_title'] ?></option>
<option value="body" <?php echo ($place == 'body' ? " selected" : "")?>><?php echo $lang_messages['select_body'] ?></option>
</select>
<?php echo $lang_messages['text_jump_to'] ?><select name="box">
<option value="1" <?php echo ($selected == PM_INBOX ? " selected" : "")?>><?php echo $lang_messages['select_inbox'] ?></option>
<option value="-1" <?php echo ($selected == PM_SENTBOX ? " selected" : "")?>><?php echo $lang_messages['select_sentbox'] ?></option>
<?php
while ($row = mysql_fetch_assoc($res))
{
if ($row['boxnumber'] == $selected)
{
echo("<option value=\"" . $row['boxnumber'] . "\" selected>" . $row['name'] . "</option>\n");
}
else
{
echo("<option value=\"" . $row['boxnumber'] . "\">" . $row['name'] . "</option>\n");
}
}
?>
</select> <input class=btn type="submit" value=<?php echo $lang_messages['submit_go'] ?>></form>
<?php
}
function messagemenu ($selected = 1) {
	global $lang_messages;
	global $BASEURL;
	global $CURUSER;
	begin_main_frame();
	print ("<div id=\"pmboxnav\"><ul id=\"pmboxmenu\" class=\"menu\">");
	print ("<li" . ($selected == 1 ? " class=selected" : "") . "><a href=\"" . get_protocol_prefix() . $BASEURL . "/messages.php\" >".$lang_messages['text_inbox']."</a></li>");
	print ("<li" . ($selected == -1 ? " class=selected" : "") . "><a href=\"" . get_protocol_prefix() . $BASEURL . "/messages.php?action=viewmailbox&box=-1\">".$lang_messages['text_sentbox']."</a></li>");
	$res = sql_query('SELECT * FROM pmboxes WHERE userid=' . sqlesc($CURUSER['id'])) or sqlerr(__FILE__,__LINE__);
	if (mysql_num_rows($res))
		while ($row = mysql_fetch_assoc($res))
		{
		print ("<li" . ($selected == $row['boxnumber'] ? " class=selected" : "") . "><a href=\"" . get_protocol_prefix() . $BASEURL . "/messages.php?action=viewmailbox&box=".$row['boxnumber']."\">".$row['name']."</a></li>");
		}
	print ("</ul></div>");
	end_main_frame();
}
?>
